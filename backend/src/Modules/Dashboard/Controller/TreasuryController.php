<?php

declare(strict_types=1);

namespace ContaVision\Modules\Dashboard\Controller;

use ContaVision\Core\Database;
use ContaVision\Integrations\MonteCarloClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador de Proyección de Tesorería.
 *
 * Este es el ENDPOINT ESTRELLA del ERP.
 *
 * Conecta los datos contables reales de MySQL con el microservicio Python
 * de Monte Carlo para generar proyecciones probabilísticas de tesorería.
 *
 * Flujo:
 * 1. Extrae datos históricos de ingresos/gastos reales de MySQL
 * 2. Calcula medias y desviaciones automáticamente
 * 3. Envía al microservicio Python para simulación
 * 4. Guarda resultados en MySQL para consulta posterior
 * 5. Devuelve percentiles P10/P50/P90 y probabilidades de déficit
 *
 * Endpoints:
 *   POST /api/treasury/simulate     - Ejecutar simulación Monte Carlo
 *   GET  /api/treasury/projections  - Obtener proyecciones guardadas
 */
class TreasuryController
{
    private MonteCarloClient $monteCarloClient;

    public function __construct()
    {
        $this->monteCarloClient = new MonteCarloClient();
    }

    /**
     * Registra el error real en el log si APP_DEBUG está activo.
     */
    private function logError(\Throwable $e): void
    {
        if (\ContaVision\Core\Config::isDebug()) {
            error_log('[TreasuryController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * POST /api/treasury/simulate
     *
     * Ejecuta una simulación de tesorería con dos modos:
     *
     * MODO A (Automático): Solo empresa_id. El sistema calcula todo
     * desde los datos contables históricos.
     *
     *   Body JSON:
     *   {
     *     "empresa_id": 1,
     *     "horizonte_meses": 12,
     *     "num_simulaciones": 10000
     *   }
     *
     * MODO B (Manual): El usuario especifica parámetros.
     *
     *   Body JSON:
     *   {
     *     "empresa_id": 1,
     *     "saldo_inicial": 50000,
     *     "ingresos_media": 30000,
     *     "ingresos_desviacion": 8000,
     *     "gastos_media": 25000,
     *     "gastos_desviacion": 5000,
     *     "horizonte_meses": 12,
     *     "num_simulaciones": 10000,
     *     "estacionalidad": [
     *       {"mes": 7, "factor_ingreso": 0.6},
     *       {"mes": 12, "factor_ingreso": 1.4}
     *     ]
     *   }
     *
     * Response 200:
     * {
     *   "success": true,
     *   "simulacion_id": 5,
     *   "duracion_ms": 342,
     *   "meses": [
     *     {"mes": 1, "p10": 45000, "p50": 55000, "p90": 65000, "prob_deficit": 0.03},
     *     {"mes": 2, "p10": 40000, "p50": 60000, "p90": 78000, "prob_deficit": 0.05},
     *     ...
     *   ],
     *   "global": {
     *     "prob_deficit_total": 0.08,
     *     "mes_critico": 3,
     *     "mejor_escenario": 120000,
     *     "peor_escenario": -15000
     *   }
     * }
     */
    public function simulate(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (empty($body['empresa_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'empresa_id es requerido'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $empresaId = (int) $body['empresa_id'];
        $db = Database::getInstance();

        // ==========================================
        // MODO A: Calcular parámetros desde datos históricos
        // ==========================================
        if (empty($body['ingresos_media'])) {
            $parametros = $this->calcularParametrosDesdeHistorico($db, $empresaId);

            // Si no hay datos históricos, devolver error
            if ($parametros === null) {
                $response->getBody()->write(json_encode([
                    'error' => 'No hay datos históricos suficientes. Proporcione los parámetros manualmente o registre más asientos contables.',
                    'minimo_asientos' => 6
                ]));
                return $response->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
        } else {
            // MODO B: Usar parámetros proporcionados por el usuario
            $parametros = [
                'saldo_inicial' => (float) $body['saldo_inicial'],
                'ingresos_media' => (float) $body['ingresos_media'],
                'ingresos_desviacion' => (float) $body['ingresos_desviacion'],
                'gastos_media' => (float) $body['gastos_media'],
                'gastos_desviacion' => (float) $body['gastos_desviacion'],
                'horizonte_meses' => (int) ($body['horizonte_meses'] ?? 12),
                'num_simulaciones' => (int) ($body['num_simulaciones'] ?? 10000),
                'estacionalidad' => $body['estacionalidad'] ?? [],
            ];
        }

        // Validar parámetros antes de enviar
        if ($parametros['ingresos_desviacion'] < 0 || $parametros['gastos_desviacion'] < 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Las desviaciones no pueden ser negativas'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        if ($parametros['horizonte_meses'] < 1 || $parametros['horizonte_meses'] > 36) {
            $response->getBody()->write(json_encode([
                'error' => 'El horizonte debe ser entre 1 y 36 meses'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // ==========================================
        // Llamar al microservicio Python
        // ==========================================
        try {
            $resultado = $this->monteCarloClient->simulateCashflow($parametros);
        } catch (\Throwable $e) {
            $this->logError($e);
            $msg = \ContaVision\Core\Config::isDebug()
                ? 'Error en el servicio de simulaciones: ' . $e->getMessage()
                : 'Error interno del servidor';
            $response->getBody()->write(json_encode(['error' => $msg]));
            return $response->withStatus(502)->withHeader('Content-Type', 'application/json');
        }

        if (!$resultado['success']) {
            $this->logError(new \Exception('Error microservicio: ' . ($resultado['error'] ?? 'unknown')));
            $response->getBody()->write(json_encode([
                'error' => 'Error en el servicio de simulaciones: ' . ($resultado['error'] ?? ''),
                'sugerencia' => 'Verifique que el microservicio Python esté ejecutándose en ' .
                    (\ContaVision\Core\Config::get('MONTE_CARLO_URL') ?? 'http://localhost:8000')
            ]));
            return $response->withStatus(502)
                ->withHeader('Content-Type', 'application/json');
        }

        // ==========================================
        // Guardar configuración y resultados en MySQL
        // ==========================================
        try {
            Database::transaction(function ($db) use (
                $empresaId, $parametros, $resultado
            ): void {
                // Guardar configuración de simulación
                $stmt = $db->prepare('
                    INSERT INTO configuraciones_simulacion
                        (empresa_id, nombre, horizonte_meses, saldo_inicial,
                         ingresos_media_mensual, ingresos_desviacion,
                         gastos_media_mensual, gastos_desviacion,
                         num_simulaciones, estacionalidad)
                    VALUES
                        (:empresa_id, :nombre, :horizonte, :saldo,
                         :ing_media, :ing_desv, :gas_media, :gas_desv,
                         :num_sim, :estacionalidad)
                ');
                $stmt->execute([
                    'empresa_id' => $empresaId,
                    'nombre' => 'Proyección automática ' . date('Y-m-d H:i'),
                    'horizonte' => $parametros['horizonte_meses'],
                    'saldo' => $parametros['saldo_inicial'],
                    'ing_media' => $parametros['ingresos_media'],
                    'ing_desv' => $parametros['ingresos_desviacion'],
                    'gas_media' => $parametros['gastos_media'],
                    'gas_desv' => $parametros['gastos_desviacion'],
                    'num_sim' => $parametros['num_simulaciones'],
                    'estacionalidad' => json_encode($parametros['estacionalidad'])
                ]);

                $configId = (int) $db->lastInsertId();

                // Guardar resultados
                $stmt = $db->prepare('
                    INSERT INTO resultados_simulacion
                        (configuracion_id, duracion_ms, resultados)
                    VALUES
                        (:config_id, :duracion, :resultados)
                ');
                $stmt->execute([
                    'config_id' => $configId,
                    'duracion' => $resultado['duration_ms'],
                    'resultados' => json_encode($resultado['data'])
                ]);
            });
        } catch (\Throwable $e) {
            // Si falla el guardado, los datos de la simulación siguen siendo válidos
            error_log('[TreasuryController::simulate] Error guardando resultados: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        // ==========================================
        // Devolver resultados al frontend
        // ==========================================
        $responseData = $resultado['data'];
        $responseData['duracion_ms'] = $resultado['duration_ms'];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Calcula los parámetros de simulación a partir de los datos contables
     * históricos de la empresa (últimos 12 meses de asientos).
     *
     * Extrae:
     * - Saldo actual (último saldo del plan de cuentas)
     * - Media y desviación de ingresos mensuales (cuentas tipo 'ingreso')
     * - Media y desviación de gastos mensuales (cuentas tipo 'gasto')
     *
     * @return array|null Parámetros o null si no hay datos suficientes
     */
    private function calcularParametrosDesdeHistorico(\PDO $db, int $empresaId): ?array
    {
        // Obtener saldo actual (sumando todos los saldos de cuentas)
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(saldo_actual), 0) as saldo_total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id
        ');
        $stmt->execute(['empresa_id' => $empresaId]);
        $saldoActual = (float) $stmt->fetch()['saldo_total'];

        // Obtener flujos mensuales de ingresos y gastos (últimos 12 meses)
        // Se agrupan por mes usando las fechas de los asientos
        $stmt = $db->prepare('
            SELECT
                DATE_FORMAT(a.fecha, "%Y-%m") as mes,
                SUM(CASE WHEN pc.tipo = "ingreso" THEN la.haber - la.debe ELSE 0 END) as total_ingresos,
                SUM(CASE WHEN pc.tipo = "gasto" THEN la.debe - la.haber ELSE 0 END) as total_gastos
            FROM lineas_asiento la
            JOIN asientos_contables a ON la.asiento_id = a.id
            JOIN plan_cuentas pc ON la.cuenta_id = pc.id
            WHERE a.empresa_id = :empresa_id
              AND a.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(a.fecha, "%Y-%m")
            ORDER BY mes ASC
        ');
        $stmt->execute(['empresa_id' => $empresaId]);
        $flujosMensuales = $stmt->fetchAll();

        // Necesitamos al menos 6 meses de datos para una simulación significativa
        if (count($flujosMensuales) < 6) {
            return null;
        }

        $ingresos = array_map(fn($f) => (float) $f['total_ingresos'], $flujosMensuales);
        $gastos = array_map(fn($f) => (float) $f['total_gastos'], $flujosMensuales);

        $ingresosMedia = array_sum($ingresos) / count($ingresos);
        $gastosMedia = array_sum($gastos) / count($gastos);

        // Calcular desviación estándar
        $ingresosVarianza = array_reduce($ingresos, fn($carry, $val) =>
            $carry + pow($val - $ingresosMedia, 2), 0) / count($ingresos);
        $gastosVarianza = array_reduce($gastos, fn($carry, $val) =>
            $carry + pow($val - $gastosMedia, 2), 0) / count($gastos);

        $ingresosDesviacion = sqrt($ingresosVarianza);
        $gastosDesviacion = sqrt($gastosVarianza);

        // Evitar desviación 0 (si los flujos son constantes)
        $ingresosDesviacion = max($ingresosDesviacion, $ingresosMedia * 0.1);
        $gastosDesviacion = max($gastosDesviacion, $gastosMedia * 0.1);

        // Detectar estacionalidad (meses que consistentemente se desvían >20%)
        $estacionalidad = [];
        foreach ($flujosMensuales as $flujo) {
            $mesNumero = (int) date('n', strtotime($flujo['mes'] . '-01'));
            $factorIngreso = ($ingresosMedia > 0)
                ? (float) $flujo['total_ingresos'] / $ingresosMedia
                : 1.0;

            if (abs($factorIngreso - 1.0) > 0.2) {
                $estacionalidad[] = [
                    'mes' => $mesNumero,
                    'factor_ingreso' => round($factorIngreso, 2)
                ];
            }
        }

        return [
            'saldo_inicial' => $saldoActual,
            'ingresos_media' => round($ingresosMedia, 2),
            'ingresos_desviacion' => round($ingresosDesviacion, 2),
            'gastos_media' => round($gastosMedia, 2),
            'gastos_desviacion' => round($gastosDesviacion, 2),
            'horizonte_meses' => 12,
            'num_simulaciones' => 10000,
            'estacionalidad' => $estacionalidad,
        ];
    }

    /**
     * GET /api/treasury/projections
     *
     * Obtiene las proyecciones guardadas de una empresa.
     */
    public function getProjections(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $params = $request->getQueryParams();
        $empresaId = (int) ($params['empresa_id'] ?? 0);

        if ($empresaId <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'empresa_id es requerido'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT rs.id, cs.nombre, cs.horizonte_meses, cs.saldo_inicial,
                   cs.ingresos_media_mensual, cs.gastos_media_mensual,
                   cs.num_simulaciones, rs.fecha_ejecucion, rs.duracion_ms,
                   rs.resultados
            FROM resultados_simulacion rs
            JOIN configuraciones_simulacion cs ON rs.configuracion_id = cs.id
            WHERE cs.empresa_id = :empresa_id
            ORDER BY rs.fecha_ejecucion DESC
            LIMIT 20
        ');
        $stmt->execute(['empresa_id' => $empresaId]);
        $projections = $stmt->fetchAll();

        // Parsear JSON de resultados
        foreach ($projections as &$p) {
            $p['resultados'] = json_decode($p['resultados'], true);
        }

        $response->getBody()->write(json_encode([
            'projections' => $projections
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/treasury/health
     *
     * Verifica que el microservicio Python esté disponible.
     */
    public function healthCheck(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $healthy = $this->monteCarloClient->healthCheck();

        $response->getBody()->write(json_encode([
            'monte_carlo_service' => $healthy ? 'online' : 'offline',
            'url' => $_ENV['MONTE_CARLO_URL'] ?? 'http://localhost:8000'
        ]));

        return $response->withStatus($healthy ? 200 : 503)
            ->withHeader('Content-Type', 'application/json');
    }
}
