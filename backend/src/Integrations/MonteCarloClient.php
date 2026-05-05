<?php

declare(strict_types=1);

namespace ContaVision\Integrations;

/**
 * Cliente HTTP para comunicarse con el microservicio de Monte Carlo (Python/FastAPI).
 *
 * Este servicio envía los parámetros de simulación y recibe los resultados
 * de la proyección de tesorería con percentiles P10, P50, P90 y
 * probabilidades de déficit por mes.
 *
 * Usa cURL nativo de PHP para evitar dependencias externas.
 *
 * Ejemplo de uso:
 *
 *   $client = new MonteCarloClient();
 *   $resultados = $client->simulateCashflow([
 *       'saldo_inicial' => 50000,
 *       'ingresos_media' => 30000,
 *       'ingresos_desviacion' => 8000,
 *       'gastos_media' => 25000,
 *       'gastos_desviacion' => 5000,
 *       'horizonte_meses' => 12,
 *       'num_simulaciones' => 10000,
 *       'estacionalidad' => [
 *           ['mes' => 7, 'factor_ingreso' => 0.6],  // Julio: menos ingresos
 *           ['mes' => 12, 'factor_ingreso' => 1.4]  // Diciembre: más ingresos
 *       ]
 *   ]);
 *
 *   // $resultados['meses'][0]['p50'] => 55000 (mediana mes 1)
 *   // $resultados['global']['prob_deficit_total'] => 0.08 (8% probabilidad de quiebra)
 */
class MonteCarloClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?? ($_ENV['MONTE_CARLO_URL'] ?? 'http://localhost:8000');
        $this->timeout = 30; // 30 segundos máximo
    }

    /**
     * Envía una solicitud de simulación de flujo de caja al microservicio Python.
     *
     * @param array{
     *   saldo_inicial: float,
     *   ingresos_media: float,
     *   ingresos_desviacion: float,
     *   gastos_media: float,
     *   gastos_desviacion: float,
     *   horizonte_meses: int,
     *   num_simulaciones: int,
     *   estacionalidad?: array<array{mes: int, factor_ingreso: float, factor_gasto: float}>
     * } $parametros
     *
     * @return array{
     *   success: bool,
     *   data?: array,
     *   error?: string,
     *   duration_ms?: int
     * }
     */
    public function simulateCashflow(array $parametros): array
    {
        $startTime = microtime(true);

        $url = rtrim($this->baseUrl, '/') . '/simulate-cashflow';

        $payload = json_encode([
            'saldo_inicial' => $parametros['saldo_inicial'],
            'ingresos_media' => $parametros['ingresos_media'],
            'ingresos_desviacion' => $parametros['ingresos_desviacion'],
            'gastos_media' => $parametros['gastos_media'],
            'gastos_desviacion' => $parametros['gastos_desviacion'],
            'horizonte_meses' => $parametros['horizonte_meses'],
            'num_simulaciones' => $parametros['num_simulaciones'],
            'estacionalidad' => $parametros['estacionalidad'] ?? [],
        ]);

        // Configurar cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Contavision-Client: PHP-Backend/v1.0'
            ],
        ]);

        $httpResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Error de conexión
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'No se pudo conectar con el servicio de simulaciones: ' . $curlError,
                'duration_ms' => $durationMs
            ];
        }

        // Error HTTP
        if ($httpCode >= 400) {
            $errorBody = json_decode($httpResponse, true);
            return [
                'success' => false,
                'error' => $errorBody['detail'] ?? $errorBody['error'] ?? "Error HTTP $httpCode",
                'duration_ms' => $durationMs
            ];
        }

        // Respuesta exitosa
        $data = json_decode($httpResponse, true);
        if ($data === null) {
            return [
                'success' => false,
                'error' => 'Respuesta inválida del servicio de simulaciones',
                'duration_ms' => $durationMs
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'duration_ms' => $durationMs
        ];
    }

    /**
     * Obtiene el historial de simulaciones almacenadas en el microservicio.
     */
    public function getSimulationHistory(int $limit = 10): array
    {
        $url = rtrim($this->baseUrl, '/') . '/simulations?limit=' . $limit;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $httpResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || $httpResponse === false) {
            return ['success' => false, 'error' => "Error HTTP $httpCode"];
        }

        return ['success' => true, 'data' => json_decode($httpResponse, true)];
    }

    /**
     * Verifica que el microservicio esté disponible (healthcheck).
     */
    public function healthCheck(): bool
    {
        $url = rtrim($this->baseUrl, '/') . '/health';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $httpResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }
}
