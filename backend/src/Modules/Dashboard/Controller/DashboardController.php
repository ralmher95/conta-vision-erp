<?php

declare(strict_types=1);

namespace ContaVision\Modules\Dashboard\Controller;

use ContaVision\Core\Database;
use ContaVision\Modules\Dashboard\Service\KpiCalculator;

/**
 * Controlador para el dashboard de KPIs financieros.
 *
 * Todas las rutas requieren autenticación JWT y permiso dashboard.read.
 */
class DashboardController
{
    /**
     * GET /api/dashboard/kpis?empresa_id=1
     *
     * Devuelve los KPIs financieros calculados para la empresa especificada.
     */
    public function kpis($request, $response, $args)
    {
        try {
            $params = $request->getQueryParams();
            $empresaId = (int) ($params['empresa_id'] ?? 0);

            if ($empresaId <= 0) {
                $payload = json_encode(['error' => 'empresa_id es requerido']);
                $response->getBody()->write($payload);
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $calculator = new KpiCalculator($empresaId);
            $kpis = $calculator->calcularTodos();

            $payload = json_encode($kpis);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $this->logError($e);
            $payload = json_encode(['error' => 'Error interno del servidor']);
            $response->getBody()->write($payload);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Registra el error real en el log si APP_DEBUG está activo.
     */
    private function logError(\Throwable $e): void
    {
        if (($GLOBALS['APP_DEBUG'] ?? false) || ($_ENV['APP_DEBUG'] ?? false)) {
            error_log('[DashboardController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
