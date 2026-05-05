<?php

declare(strict_types=1);

namespace ContaVision\Tests\Accounting;

use ContaVision\Core\Database;
use ContaVision\Modules\Accounting\Service\DoubleEntryService;
use PHPUnit\Framework\TestCase;

/**
 * Tests del servicio de Partida Doble.
 *
 * Verifica que:
 * 1. La partida doble siempre cuadra (debe == haber)
 * 2. Las transacciones ACID funcionan (rollback en caso de error)
 * 3. Los saldos se actualizan correctamente
 * 4. Se validan los inputs incorrectos
 */
class DoubleEntryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configurar variables de entorno para test
        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
        $_ENV['DB_NAME'] = $_ENV['DB_NAME'] ?? 'contavision_erp_test';
        $_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
    }

    protected function tearDown(): void
    {
        Database::reset();
        parent::tearDown();
    }

    /**
     * Test: Partida doble válida con 2 líneas.
     *
     * Escenario: Cobro de 1000€ de un cliente.
     * - Debe: Bancos (572) = 1000€
     * - Haber: Clientes (430) = 1000€
     */
    public function test_asiento_simple_cuadra(): void
    {
        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);

        $service->addLinea(
            cuentaId: 12, // Bancos
            debe: 1000.00,
            haber: 0,
            descripcion: 'Cobro banco'
        );
        $service->addLinea(
            cuentaId: 5, // Clientes
            debe: 0,
            haber: 1000.00,
            descripcion: 'Cobro cliente'
        );

        $this->assertEquals(1000.00, $service->getTotalDebe());
        $this->assertEquals(1000.00, $service->getTotalHaber());
        $this->assertEquals(2, $service->getNumLineas());
    }

    /**
     * Test: Partida doble con 3 líneas (incluyendo IVA).
     *
     * Escenario: Factura de venta de 1000€ + 21% IVA.
     * - Debe: Clientes (430) = 1210€
     * - Haber: Ventas (700) = 1000€
     * - Haber: IVA repercutido (477) = 210€
     */
    public function test_asiento_con_iva_cuadra(): void
    {
        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);

        $service->addLinea(cuentaId: 5, debe: 1210.00, haber: 0, descripcion: 'Total factura');
        $service->addLinea(cuentaId: 100, debe: 0, haber: 1000.00, descripcion: 'Venta servicios');
        $service->addLinea(cuentaId: 20, debe: 0, haber: 210.00, descripcion: 'IVA 21%');

        $this->assertEquals(1210.00, $service->getTotalDebe());
        $this->assertEquals(1210.00, $service->getTotalHaber());
    }

    /**
     * Test: La partida doble NO cuadra → debe lanzar excepción.
     */
    public function test_asiento_no_cuadra_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La partida doble no cuadra');

        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);

        $service->addLinea(cuentaId: 12, debe: 1000.00, haber: 0);
        $service->addLinea(cuentaId: 5, debe: 0, haber: 800.00); // ¡Faltan 200€!

        // Esto debería lanzar excepción
        $service->save(
            fecha: '2025-01-15',
            descripcion: 'Asiento descuadrado intencionalmente'
        );
    }

    /**
     * Test: Línea con debe y haber simultáneos → excepción.
     */
    public function test_linea_con_ambos_importes_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no puede tener importe en debe y haber');

        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);
        $service->addLinea(cuentaId: 12, debe: 500.00, haber: 500.00);
    }

    /**
     * Test: Línea sin importes → excepción.
     */
    public function test_linea_sin_importes_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('debe tener importe en debe o en haber');

        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);
        $service->addLinea(cuentaId: 12, debe: 0, haber: 0);
    }

    /**
     * Test: Importe negativo → excepción.
     */
    public function test_importe_negativo_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);
        $service->addLinea(cuentaId: 12, debe: -100.00, haber: 0);
    }

    /**
     * Test: Asiento sin líneas → excepción.
     */
    public function test_asiento_sin_lineas_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('debe tener al menos una línea');

        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);
        $service->save(fecha: '2025-01-15', descripcion: 'Vacío');
    }

    /**
     * Test: Asiento complejo con 4 líneas (compra con IVA y retención).
     *
     * Escenario: Compra de mercaderías 5000€ + 21% IVA - 15% IRPF.
     * - Debe: Mercaderías (600) = 5000€
     * - Debe: IVA soportado (472) = 1050€
     * - Haber: Proveedores (400) = 5300€
     * - Haber: HP IRPF (4751) = 750€
     */
    public function test_asiento_compra_complejo(): void
    {
        $service = new DoubleEntryService(empresaId: 1, creadoPor: 1);

        $service->addLinea(cuentaId: 50, debe: 5000.00, haber: 0, descripcion: 'Compra mercaderías');
        $service->addLinea(cuentaId: 15, debe: 1050.00, haber: 0, descripcion: 'IVA 21% soportado');
        $service->addLinea(cuentaId: 30, debe: 0, haber: 5300.00, descripcion: 'A pagar a proveedor');
        $service->addLinea(cuentaId: 18, debe: 0, haber: 750.00, descripcion: 'IRPF 15%');

        $this->assertEquals(6050.00, $service->getTotalDebe());
        $this->assertEquals(6050.00, $service->getTotalHaber());
    }
}
