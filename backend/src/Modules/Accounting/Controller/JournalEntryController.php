<?php

declare(strict_types=1);

namespace ContaVision\Modules\Accounting\Controller;

use ContaVision\Core\Database;
use ContaVision\Modules\Accounting\Service\DoubleEntryService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador de Asientos Contables (Libro Diario).
 *
 * Endpoints:
 *   GET    /api/asientos           - Listar asientos (con filtros)
 *   GET    /api/asientos/{id}      - Obtener asiento con sus líneas
 *   POST   /api/asientos           - Crear asiento contable (partida doble)
 *   DELETE /api/asientos/{id}      - Anular asiento
 */
class JournalEntryController
{
    /**
     * GET /api/asientos
     *
     * Query params opcionales:
     *   ?empresa_id=1&fecha_desde=2025-01-01&fecha_hasta=2025-12-31
     *   &tipo=ordinario&pagina=1&por_pagina=50
     *
     * Response 200:
     * {
     *   "asientos": [...],
     *   "total": 150,
     *   "pagina": 1,
     *   "por_pagina": 50
     * }
     */
    public function index(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $params = $request->getQueryParams();
        $empresaId = (int) ($params['empresa_id'] ?? 0);
        $pagina = (int) ($params['pagina'] ?? 1);
        $porPagina = (int) ($params['por_pagina'] ?? 50);
        $offset = ($pagina - 1) * $porPagina;

        if ($empresaId <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'empresa_id es requerido'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();

        // Construir consulta con filtros dinámicos
        $where = ['a.empresa_id = :empresa_id'];
        $binds = ['empresa_id' => $empresaId];

        if (!empty($params['fecha_desde'])) {
            $where[] = 'a.fecha >= :fecha_desde';
            $binds['fecha_desde'] = $params['fecha_desde'];
        }
        if (!empty($params['fecha_hasta'])) {
            $where[] = 'a.fecha <= :fecha_hasta';
            $binds['fecha_hasta'] = $params['fecha_hasta'];
        }
        if (!empty($params['tipo'])) {
            $where[] = 'a.tipo = :tipo';
            $binds['tipo'] = $params['tipo'];
        }
        if (!empty($params['conciliado'])) {
            $where[] = 'a.conciliado = :conciliado';
            $binds['conciliado'] = (int) $params['conciliado'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM asientos_contables a WHERE $whereClause");
        $stmt->execute($binds);
        $total = (int) $stmt->fetch()['total'];

        // Fetch asientos
        $stmt = $db->prepare("
            SELECT a.id, a.numero, a.fecha, a.descripcion, a.tipo, a.ejercicio_fiscal,
                   a.total_debe, a.total_haber, a.conciliado,
                   u.nombre_completo as creado_por_nombre,
                   a.created_at
            FROM asientos_contables a
            LEFT JOIN users u ON a.creado_por = u.id
            WHERE $whereClause
            ORDER BY a.fecha DESC, a.numero DESC
            LIMIT :limit OFFSET :offset
        ");

        // Bind limit y offset
        foreach ($binds as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit', $porPagina, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $asientos = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'asientos' => $asientos,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) ceil($total / $porPagina)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/asientos/{id}
     *
     * Devuelve un asiento con todas sus líneas.
     *
     * Response 200:
     * {
     *   "asiento": {
     *     "id": 1,
     *     "numero": 1,
     *     "fecha": "2025-01-15",
     *     "descripcion": "Cobro a cliente Pérez",
     *     "tipo": "banco",
     *     "total_debe": 1210.00,
     *     "total_haber": 1210.00,
     *     "lineas": [
     *       { "cuenta_codigo": "5720000000", "cuenta_descripcion": "Bancos", "debe": 1210.00, "haber": 0 },
     *       { "cuenta_codigo": "4300000000", "cuenta_descripcion": "Clientes", "debe": 0, "haber": 1000.00 },
     *       { "cuenta_codigo": "4770000000", "cuenta_descripcion": "HP IVA repercutido", "debe": 0, "haber": 210.00 }
     *     ]
     *   }
     * }
     */
    public function show(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];

        $db = Database::getInstance();

        // Obtener cabecera del asiento
        $stmt = $db->prepare('
            SELECT a.*, u.nombre_completo as creado_por_nombre
            FROM asientos_contables a
            LEFT JOIN users u ON a.creado_por = u.id
            WHERE a.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $asiento = $stmt->fetch();

        if (!$asiento) {
            $response->getBody()->write(json_encode([
                'error' => 'Asiento no encontrado'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Obtener líneas del asiento
        $stmt = $db->prepare('
            SELECT la.debe, la.haber, la.descripcion, la.referencia,
                   pc.codigo as cuenta_codigo, pc.descripcion as cuenta_descripcion,
                   pc.tipo as cuenta_tipo
            FROM lineas_asiento la
            JOIN plan_cuentas pc ON la.cuenta_id = pc.id
            WHERE la.asiento_id = :asiento_id
            ORDER BY la.id ASC
        ');
        $stmt->execute(['asiento_id' => $id]);
        $lineas = $stmt->fetchAll();

        $asiento['lineas'] = $lineas;

        $response->getBody()->write(json_encode(['asiento' => $asiento]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/asientos
     *
     * Crea un asiento contable con partida doble.
     *
     * Body JSON:
     * {
     *   "empresa_id": 1,
     *   "fecha": "2025-01-15",
     *   "descripcion": "Cobro factura F-2025-001",
     *   "tipo": "banco",
     *   "lineas": [
     *     { "cuenta_id": 12, "debe": 1210.00, "haber": 0, "descripcion": "Cobro banco", "referencia": "F-2025-001" },
     *     { "cuenta_id": 5, "debe": 0, "haber": 1000.00, "descripcion": "Cobro cliente" },
     *     { "cuenta_id": 20, "debe": 0, "haber": 210.00, "descripcion": "IVA 21%" }
     *   ]
     * }
     *
     * Response 201:
     * {
     *   "message": "Asiento creado exitosamente",
     *   "asiento_id": 42,
     *   "numero": 15,
     *   "total_debe": 1210.00,
     *   "total_haber": 1210.00
     * }
     */
    public function store(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        // Validaciones básicas
        if (empty($body['empresa_id']) || empty($body['fecha']) || empty($body['lineas'])) {
            $response->getBody()->write(json_encode([
                'error' => 'empresa_id, fecha y lineas son requeridos'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $userId = $request->getAttribute('user_id');

        try {
            // Crear servicio de partida doble
            $service = new DoubleEntryService(
                (int) $body['empresa_id'],
                $userId
            );

            // Añadir cada línea
            foreach ($body['lineas'] as $lineaData) {
                $service->addLinea(
                    cuentaId: (int) $lineaData['cuenta_id'],
                    debe: (float) ($lineaData['debe'] ?? 0),
                    haber: (float) ($lineaData['haber'] ?? 0),
                    descripcion: $lineaData['descripcion'] ?? null,
                    referencia: $lineaData['referencia'] ?? null
                );
            }

            // Guardar con transacción ACID
            $asientoId = $service->save(
                fecha: $body['fecha'],
                descripcion: $body['descripcion'] ?? '',
                tipo: $body['tipo'] ?? 'ordinario',
                facturaId: $body['factura_id'] ?? null
            );

            // Registrar en audit log
            $db = Database::getInstance();
            $stmt = $db->prepare('
                INSERT INTO audit_log
                    (empresa_id, user_id, tabla_afectada, registro_id, accion, valores_nuevos)
                VALUES
                    (:empresa_id, :user_id, "asientos_contables", :registro_id, "create", :valores)
            ');
            $stmt->execute([
                'empresa_id' => $body['empresa_id'],
                'user_id' => $userId,
                'registro_id' => $asientoId,
                'valores' => json_encode([
                    'fecha' => $body['fecha'],
                    'descripcion' => $body['descripcion'],
                    'total_debe' => $service->getTotalDebe(),
                    'total_haber' => $service->getTotalHaber()
                ])
            ]);

            $response->getBody()->write(json_encode([
                'message' => 'Asiento creado exitosamente',
                'asiento_id' => $asientoId,
                'numero' => null, // Se obtiene en el siguiente GET
                'total_debe' => $service->getTotalDebe(),
                'total_haber' => $service->getTotalHaber()
            ]));
            return $response->withStatus(201)
                ->withHeader('Content-Type', 'application/json');

        } catch (\InvalidArgumentException $e) {
            // Error de validación (partida doble no cuadra, etc.)
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            // Log del error real
            error_log('[JournalEntryController::store] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            // Mensaje genérico para el cliente
            $errorMsg = \ContaVision\Core\Config::isDebug()
                ? 'Error interno al crear el asiento: ' . $e->getMessage()
                : 'Error interno del servidor';
            $response->getBody()->write(json_encode([
                'error' => $errorMsg
            ]));
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * DELETE /api/asientos/{id}
     *
     * Anula un asiento (soft delete con audit trail).
     */
    public function delete(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $userId = $request->getAttribute('user_id');

        $db = Database::getInstance();

        // Verificar que existe
        $stmt = $db->prepare('SELECT empresa_id FROM asientos_contables WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $asiento = $stmt->fetch();

        if (!$asiento) {
            $response->getBody()->write(json_encode([
                'error' => 'Asiento no encontrado'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Registrar en audit log antes de borrar
        $stmt = $db->prepare('
            INSERT INTO audit_log
                (empresa_id, user_id, tabla_afectada, registro_id, accion, valores_anteriores)
            VALUES
                (:empresa_id, :user_id, "asientos_contables", :registro_id, "delete", :valores)
        ');
        $stmt->execute([
            'empresa_id' => $asiento['empresa_id'],
            'user_id' => $userId,
            'registro_id' => $id,
            'valores' => json_encode(['asiento_id' => $id])
        ]);

        // Las líneas se borran en cascada (ON DELETE CASCADE)
        $stmt = $db->prepare('DELETE FROM asientos_contables WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $response->getBody()->write(json_encode([
            'message' => 'Asiento anulado exitosamente'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
