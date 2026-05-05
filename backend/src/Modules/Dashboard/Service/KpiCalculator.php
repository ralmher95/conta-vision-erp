<?php

declare(strict_types=1);

namespace ContaVision\Modules\Dashboard\Service;

use ContaVision\Core\Database;

/**
 * Calculadora de KPIs financieros.
 *
 * Extrae datos directamente de las tablas contables y calcula:
 * - Liquidez corriente = Activo corriente / Pasivo corriente
 * - Fondo de maniobra = Activo corriente - Pasivo corriente
 * - Ratio de endeudamiento = Pasivo total / Activo total
 * - Rentabilidad económica = BAIT / Activo total
 * - Rentabilidad financiera = Beneficio neto / Patrimonio neto
 * - Cuentas por cobrar/pagar
 * - Tesorería actual
 * - Facturas vencidas y pendientes
 */
class KpiCalculator
{
    private int $empresaId;

    public function __construct(int $empresaId)
    {
        $this->empresaId = $empresaId;
    }

    /**
     * Calcula todos los KPIs financieros de la empresa.
     *
     * @return array<string, float>
     */
    public function calcularTodos(): array
    {
        return [
            'liquidez_corriente' => $this->calcularLiquidezCorriente(),
            'fondo_manobra' => $this->calcularFondoManiobra(),
            'ratio_endeudamiento' => $this->calcularRatioEndeudamiento(),
            'rentabilidad_economica' => $this->calcularRentabilidadEconomica(),
            'rentabilidad_financiera' => $this->calcularRentabilidadFinanciera(),
            'cuentas_cobrar' => $this->calcularCuentasCobrar(),
            'cuentas_pagar' => $this->calcularCuentasPagar(),
            'tesoreria_actual' => $this->calcularTesoreriaActual(),
            'facturas_vencidas' => $this->calcularFacturasVencidas(),
            'facturas_pendientes_cobro' => $this->calcularFacturasPendientesCobro(),
        ];
    }

    /**
     * Liquidez corriente = Activo corriente / Pasivo corriente
     *
     * Ratio > 1 indica que la empresa puede cubrir sus deudas a corto plazo.
     */
    private function calcularLiquidezCorriente(): float
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id
              AND tipo = "activo"
              AND LENGTH(codigo) >= 3
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $activoCorriente = (float) $stmt->fetch()['total'];

        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id
              AND tipo = "pasivo"
              AND LENGTH(codigo) >= 3
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $pasivoCorriente = (float) $stmt->fetch()['total'];

        if ($pasivoCorriente == 0) return 0;

        return round($activoCorriente / $pasivoCorriente, 4);
    }

    /**
     * Fondo de maniobra = Activo corriente - Pasivo corriente
     */
    private function calcularFondoManiobra(): float
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id AND tipo = "activo"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $activo = (float) $stmt->fetch()['total'];

        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id AND tipo = "pasivo"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $pasivo = (float) $stmt->fetch()['total'];

        return round($activo - $pasivo, 2);
    }

    /**
     * Ratio de endeudamiento = Pasivo total / Activo total
     *
     * Ratio < 0.6 es saludable. > 0.8 indica alto riesgo.
     */
    private function calcularRatioEndeudamiento(): float
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT SUM(ABS(saldo_actual)) as total_pasivo
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id AND tipo = "pasivo"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $pasivoTotal = (float) $stmt->fetch()['total_pasivo'];

        $stmt = $db->prepare('
            SELECT SUM(ABS(saldo_actual)) as total_activo
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id AND tipo = "activo"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $activoTotal = (float) $stmt->fetch()['total_activo'];

        if ($activoTotal == 0) return 0;

        return round($pasivoTotal / $activoTotal, 4);
    }

    /**
     * Rentabilidad económica = BAIT (EBIT) / Activo total
     *
     * BAIT = Ingresos - Gastos de explotación
     */
    private function calcularRentabilidadEconomica(): float
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT
                SUM(CASE WHEN pc.tipo = "ingreso" THEN la.haber - la.debe ELSE 0 END)
                -
                SUM(CASE WHEN pc.tipo = "gasto" THEN la.debe - la.haber ELSE 0 END)
                as bait
            FROM lineas_asiento la
            JOIN asientos_contables a ON la.asiento_id = a.id
            JOIN plan_cuentas pc ON la.cuenta_id = pc.id
            WHERE a.empresa_id = :empresa_id
              AND a.ejercicio_fiscal = YEAR(CURDATE())
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $bait = (float) $stmt->fetch()['bait'];

        $stmt = $db->prepare('
            SELECT SUM(ABS(saldo_actual)) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id AND tipo = "activo"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $activoTotal = (float) $stmt->fetch()['total'];

        if ($activoTotal == 0) return 0;

        return round($bait / $activoTotal, 4);
    }

    /**
     * Rentabilidad financiera = Beneficio neto / Patrimonio neto
     */
    private function calcularRentabilidadFinanciera(): float
    {
        $db = Database::getInstance();

        // Beneficio neto = Ingresos - Gastos
        $stmt = $db->prepare('
            SELECT
                SUM(CASE WHEN pc.tipo = "ingreso" THEN la.haber - la.debe ELSE 0 END)
                -
                SUM(CASE WHEN pc.tipo = "gasto" THEN la.debe - la.haber ELSE 0 END)
                as beneficio
            FROM lineas_asiento la
            JOIN asientos_contables a ON la.asiento_id = a.id
            JOIN plan_cuentas pc ON la.cuenta_id = pc.id
            WHERE a.empresa_id = :empresa_id
              AND a.ejercicio_fiscal = YEAR(CURDATE())
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $beneficio = (float) $stmt->fetch()['beneficio'];

        $stmt = $db->prepare('
            SELECT SUM(ABS(saldo_actual)) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id AND tipo = "patrimonio_neto"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        $patrimonio = (float) $stmt->fetch()['total'];

        if ($patrimonio == 0) return 0;

        return round($beneficio / $patrimonio, 4);
    }

    /**
     * Cuentas por cobrar = saldo de clientes (cuentas 430xxx)
     */
    private function calcularCuentasCobrar(): float
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id
              AND tipo = "activo"
              AND codigo LIKE "43%"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        return (float) $stmt->fetch()['total'];
    }

    /**
     * Cuentas por pagar = saldo de proveedores (cuentas 400xxx)
     */
    private function calcularCuentasPagar(): float
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id
              AND tipo = "pasivo"
              AND codigo LIKE "40%"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        return (float) $stmt->fetch()['total'];
    }

    /**
     * Tesorería actual = suma de cuentas de bancos (572xxx) + caja (570xxx)
     */
    private function calcularTesoreriaActual(): float
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT SUM(saldo_actual) as total
            FROM plan_cuentas
            WHERE empresa_id = :empresa_id
              AND (codigo LIKE "570%" OR codigo LIKE "572%")
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        return (float) $stmt->fetch()['total'];
    }

    /**
     * Facturas vencidas = facturas emitidas con fecha_vencimiento < hoy y estado != pagada
     */
    private function calcularFacturasVencidas(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) as total
            FROM facturas
            WHERE empresa_id = :empresa_id
              AND fecha_vencimiento < CURDATE()
              AND estado NOT IN ("pagada", "anulada")
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Facturas pendientes de cobro = emitidas, no pagadas, no vencidas
     */
    private function calcularFacturasPendientesCobro(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) as total
            FROM facturas
            WHERE empresa_id = :empresa_id
              AND tipo = "emitida"
              AND estado = "emitida"
        ');
        $stmt->execute(['empresa_id' => $this->empresaId]);
        return (int) $stmt->fetch()['total'];
    }
}
