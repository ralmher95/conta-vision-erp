<?php

declare(strict_types=1);

namespace ContaVision\Modules\Reconciliation\Controller;

use ContaVision\Core\Database;
use ContaVision\Modules\Reconciliation\Service\OcrService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador de Conciliación Bancaria.
 *
 * Endpoints:
 *   POST   /api/conciliacion/upload          - Subir extracto bancario (OCR)
 *   GET    /api/conciliacion/pendientes       - Movimientos sin conciliar
 *   POST   /api/conciliacion/conciliar        - Conciliar movimiento con asiento
 *   GET    /api/conciliacion/extractos        - Historial de extractos
 */
class ReconciliationController
{
    private OcrService $ocrService;

    public function __construct()
    {
        $this->ocrService = new OcrService();
    }

    /**
     * POST /api/conciliacion/upload
     *
     * Sube un extracto bancario (PDF/imagen) y lo procesa con OCR.
     *
     * FormData:
     *   extracto: archivo PDF/PNG/JPG
     *   cuenta_bancaria_id: int
     *
     * Response 200:
     * {
     *   "success": true,
     *   "extracto_id": 5,
     *   "transacciones_encontradas": 23,
     *   "sugerencias_conciliacion": [
     *     {
     *       "movimiento_id": 10,
     *       "movimiento": { "fecha": "2025-01-15", "descripcion": "...", "importe": 1210.00 },
     *       "asientos_sugeridos": [...],
     *       "confianza": "alta"
     *     }
     *   ]
     * }
     */
    public function uploadExtracto(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'multipart/form-data') === false) {
            $response->getBody()->write(json_encode([
                'error' => 'Se requiere multipart/form-data para subir archivos'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $postData = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['extracto'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Se requiere un archivo "extracto"'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $cuentaBancariaId = (int) ($postData['cuenta_bancaria_id'] ?? 0);
        if ($cuentaBancariaId <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'cuenta_bancaria_id es requerido'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $file = $uploadedFiles['extracto'];

        $result = $this->ocrService->procesarExtracto([
            'name' => $file->getClientFilename(),
            'tmp_name' => $file->getStream()->getMetadata('uri'),
            'size' => $file->getSize(),
            'error' => $file->getError(),
            'type' => $file->getClientMediaType(),
        ], $cuentaBancariaId);

        if (!$result['success']) {
            $response->getBody()->write(json_encode($result));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/conciliacion/pendientes
     *
     * Devuelve movimientos bancarios sin conciliar con sugerencias.
     */
    public function getPendientes(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $params = $request->getQueryParams();
        $extractoId = (int) ($params['extracto_id'] ?? 0);

        if ($extractoId <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'extracto_id es requerido'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT mb.id, mb.fecha_operacion, mb.descripcion, mb.importe,
                   mb.conciliado, a.id as asiento_id, a.numero as asiento_numero,
                   a.descripcion as asiento_desc
            FROM movimientos_bancarios mb
            LEFT JOIN asientos_contables a ON mb.asiento_sugerido_id = a.id
            WHERE mb.extracto_id = :extracto_id
            ORDER BY mb.fecha_operacion ASC
        ');
        $stmt->execute(['extracto_id' => $extractoId]);
        $movimientos = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'movimientos' => $movimientos,
            'total' => count($movimientos),
            'conciliados' => count(array_filter($movimientos, fn($m) => $m['conciliado'])),
            'pendientes' => count(array_filter($movimientos, fn($m) => !$m['conciliado'])),
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/conciliacion/conciliar
     *
     * Concilia un movimiento bancario con un asiento contable.
     *
     * Body JSON:
     * {
     *   "movimiento_id": 10,
     *   "asiento_id": 42
     * }
     */
    public function conciliar(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (empty($body['movimiento_id']) || empty($body['asiento_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'movimiento_id y asiento_id son requeridos'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $userId = $request->getAttribute('user_id');

        $result = $this->ocrService->conciliar(
            (int) $body['movimiento_id'],
            (int) $body['asiento_id'],
            $userId
        );

        if (!$result['success']) {
            $response->getBody()->write(json_encode($result));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/conciliacion/extractos
     *
     * Historial de extractos bancarios procesados.
     */
    public function getExtractos(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $params = $request->getQueryParams();
        $cuentaId = (int) ($params['cuenta_bancaria_id'] ?? 0);

        if ($cuentaId <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'cuenta_bancaria_id es requerido'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT eb.id, eb.archivo_original, eb.fecha_subida,
                   eb.fecha_extracto_inicio, eb.fecha_extracto_fin,
                   eb.estado, eb.texto_ocr IS NOT NULL as tiene_ocr,
                   COUNT(mb.id) as total_movimientos,
                   SUM(mb.conciliado) as movimientos_conciliados
            FROM extractos_bancarios eb
            LEFT JOIN movimientos_bancarios mb ON eb.id = mb.extracto_id
            WHERE eb.cuenta_bancaria_id = :cuenta_id
            GROUP BY eb.id
            ORDER BY eb.fecha_subida DESC
        ');
        $stmt->execute(['cuenta_id' => $cuentaId]);
        $extractos = $stmt->fetchAll();

        $response->getBody()->write(json_encode(['extractos' => $extractos]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
