<?php

declare(strict_types=1);

namespace Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración para la API de Asientos Contables.
 *
 * Flujo: login → obtener token → crear asiento → verificar → crear asiento descuadrado (error 400)
 *
 * Requiere:
 * - MySQL corriendo con la base de datos contavision_erp
 * - Usuario admin@contavision.local con password admin123
 */
class JournalApiTest extends TestCase
{
    private Client $client;
    private string $baseUrl;
    private string $token = '';
    private int $empresaId = 1;

    protected function setUp(): void
    {
        $this->baseUrl = 'http://localhost:8080';
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'http_errors' => false,
            'timeout' => 10,
        ]);

        $this->login();
    }

    /**
     * Realiza login y obtiene el token JWT.
     */
    private function login(): void
    {
        $response = $this->client->post('/api/auth/login', [
            'json' => [
                'email' => 'admin@contavision.local',
                'password' => 'admin123',
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode(), 'Login fallido');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('token', $body, 'Token no encontrado en respuesta');

        $this->token = $body['token'];
    }

    /**
     * Test: Crear un asiento contable válido (partida doble cuadrada).
     */
    public function testCrearAsientoValido(): int
    {
        $response = $this->client->post('/api/asientos', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'empresa_id' => $this->empresaId,
                'fecha' => date('Y-m-d'),
                'descripcion' => 'Test asiento de integracion',
                'tipo' => 'ordinario',
                'lineas' => [
                    [
                        'cuenta_id' => 1,
                        'debe' => 1000.00,
                        'haber' => 0,
                        'descripcion' => 'Cargo a banco',
                    ],
                    [
                        'cuenta_id' => 2,
                        'debe' => 0,
                        'haber' => 1000.00,
                        'descripcion' => 'Abono a ventas',
                    ],
                ],
            ],
        ]);

        $this->assertEquals(201, $response->getStatusCode(), 'Error creando asiento');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('asiento_id', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertEquals('Asiento creado exitosamente', $body['message']);

        return $body['asiento_id'];
    }

    /**
     * Test: Verificar que el asiento se creó correctamente.
     *
     * @depends testCrearAsientoValido
     */
    public function testVerificarAsientoCreado(int $asientoId): void
    {
        $response = $this->client->get('/api/asientos/' . $asientoId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('asiento', $body);
        $this->assertEquals($asientoId, $body['asiento']['id']);
        $this->assertArrayHasKey('lineas', $body['asiento']);
        $this->assertCount(2, $body['asiento']['lineas']);
    }

    /**
     * Test: Intentar crear asiento descuadrado (debe != haber).
     * Debe devolver error 400.
     */
    public function testCrearAsientoDescuadrado(): void
    {
        $response = $this->client->post('/api/asientos', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'empresa_id' => $this->empresaId,
                'fecha' => date('Y-m-d'),
                'descripcion' => 'Asiento descuadrado test',
                'tipo' => 'ordinario',
                'lineas' => [
                    [
                        'cuenta_id' => 1,
                        'debe' => 1000.00,
                        'haber' => 0,
                        'descripcion' => 'Solo cargo',
                    ],
                    [
                        'cuenta_id' => 2,
                        'debe' => 0,
                        'haber' => 500.00,
                        'descripcion' => 'Abono incompleto',
                    ],
                ],
            ],
        ]);

        $this->assertEquals(400, $response->getStatusCode(), 'Deberia fallar por partida doble no cuadrada');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * Test: Crear asiento sin token (debe dar 401).
     */
    public function testCrearAsientoSinToken(): void
    {
        $response = $this->client->post('/api/asientos', [
            'json' => [
                'empresa_id' => $this->empresaId,
                'fecha' => date('Y-m-d'),
                'descripcion' => 'Test sin token',
                'lineas' => [],
            ],
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test: Crear asiento con datos incompletos.
     */
    public function testCrearAsientoDatosIncompletos(): void
    {
        $response = $this->client->post('/api/asientos', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'empresa_id' => $this->empresaId,
                // Falta fecha y lineas
            ],
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }
}
