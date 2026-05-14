<?php

declare(strict_types=1);

namespace ContaVision\Modules\Accounting\Controller;

use ContaVision\Core\Database;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador del Plan de Cuentas (Chart of Accounts).
 *
 * Endpoints:
 *   GET    /api/cuentas           - Listar cuentas
 *   GET    /api/cuentas/{id}      - Obtener cuenta
 *   POST   /api/cuentas           - Crear cuenta
 *   PUT    /api/cuentas/{id}      - Actualizar cuenta
 *   DELETE /api/cuentas/{id}      - Desactivar cuenta
 */
class PlanCuentaController
{
    /**
     * GET /api/cuentas?empresa_id=1&search=&tipo=
     */
    public function index(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $empresaId = (int) ($params['empresa_id'] ?? 0);

        if ($empresaId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'empresa_id es requerido']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();
        $where = ['empresa_id = :empresa_id AND activa = 1'];
        $binds = ['empresa_id' => $empresaId];

        if (!empty($params['search'])) {
            $where[] = '(codigo LIKE :search OR descripcion LIKE :search)';
            $binds['search'] = '%' . $params['search'] . '%';
        }
        if (!empty($params['tipo'])) {
            $where[] = 'tipo = :tipo';
            $binds['tipo'] = $params['tipo'];
        }

        $stmt = $db->prepare('
            SELECT * FROM plan_cuentas
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY codigo ASC
        ');
        $stmt->execute($binds);
        $cuentas = $stmt->fetchAll();

        $response->getBody()->write(json_encode(['cuentas' => $cuentas]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/cuentas/{id}
     */
    public function show(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT * FROM plan_cuentas WHERE id = :id AND activa = 1');
        $stmt->execute(['id' => $id]);
        $cuenta = $stmt->fetch();

        if (!$cuenta) {
            $response->getBody()->write(json_encode(['error' => 'Cuenta no encontrada']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['cuenta' => $cuenta]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/cuentas
     * Body: { empresa_id, codigo, descripcion, tipo, padre_id? }
     */
    public function store(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (empty($body['empresa_id']) || empty($body['codigo']) || empty($body['descripcion']) || empty($body['tipo'])) {
            $response->getBody()->write(json_encode(['error' => 'empresa_id, codigo, descripcion y tipo son requeridos']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();

        // Verificar código único
        $stmt = $db->prepare('SELECT id FROM plan_cuentas WHERE empresa_id = :empresa_id AND codigo = :codigo');
        $stmt->execute(['empresa_id' => $body['empresa_id'], 'codigo' => $body['codigo']]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'El código de cuenta ya existe']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $db->prepare('
            INSERT INTO plan_cuentas
                (empresa_id, codigo, descripcion, tipo, padre_id, nivel, activa, saldo_actual, created_at)
            VALUES
                (:empresa_id, :codigo, :descripcion, :tipo, :padre_id, :nivel, 1, 0.00, NOW())
        ');
        $stmt->execute([
            'empresa_id' => $body['empresa_id'],
            'codigo' => $body['codigo'],
            'descripcion' => $body['descripcion'],
            'tipo' => $body['tipo'],
            'padre_id' => $body['padre_id'] ?? null,
            'nivel' => $body['nivel'] ?? 1,
        ]);

        $cuentaId = $db->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => 'Cuenta creada exitosamente',
            'cuenta_id' => $cuentaId,
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    /**
     * PUT /api/cuentas/{id}
     */
    public function update(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $body = json_decode($request->getBody()->getContents(), true);
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM plan_cuentas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Cuenta no encontrada']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $fields = [];
        $binds = ['id' => $id];
        foreach (['descripcion', 'tipo', 'padre_id', 'nivel'] as $field) {
            if (isset($body[$field])) {
                $fields[] = "$field = :$field";
                $binds[$field] = $body[$field];
            }
        }

        if (!empty($fields)) {
            $stmt = $db->prepare('UPDATE plan_cuentas SET ' . implode(', ', $fields) . ' WHERE id = :id');
            $stmt->execute($binds);
        }

        $response->getBody()->write(json_encode(['message' => 'Cuenta actualizada exitosamente']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /api/cuentas/{id}
     * Soft delete: marca como inactiva
     */
    public function delete(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $db = Database::getInstance();

        $stmt = $db->prepare('UPDATE plan_cuentas SET activa = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $response->getBody()->write(json_encode(['message' => 'Cuenta desactivada exitosamente']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
