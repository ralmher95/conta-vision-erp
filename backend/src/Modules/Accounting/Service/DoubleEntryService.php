<?php

declare(strict_types=1);

namespace ContaVision\Modules\Accounting\Service;

use ContaVision\Core\Database;
use PDO;

/**
 * Servicio de Partida Doble.
 *
 * Garantiza que TODOS los asientos contables cumplan el principio
 * de partida doble: suma(debe) == suma(haber).
 *
 * Usa transacciones ACID para asegurar que:
 * 1. El asiento se crea atomícamente con TODAS sus líneas
 * 2. Si alguna línea falla, se revierte todo (ROLLBACK)
 * 3. Los saldos de las cuentas se actualizan de forma consistente
 *
 * Ejemplo de uso:
 *
 *   $service = new DoubleEntryService($empresaId, $userId);
 *   $service->addLinea(
 *       cuentaId: 12,   // 5720000000 - Bancos
 *       debe: 1000.00,
 *       haber: 0,
 *       descripcion: 'Cobro factura F-2025-001',
 *       referencia: 'F-2025-001'
 *   );
 *   $service->addLinea(
 *       cuentaId: 5,    // 4300000000 - Clientes
 *       debe: 0,
 *       haber: 1000.00,
 *       descripcion: 'Cobro factura F-2025-001',
 *       referencia: 'F-2025-001'
 *   );
 *   $asientoId = $service->save(
 *       fecha: '2025-01-15',
 *       descripcion: 'Cobro a cliente Pérez',
 *       tipo: 'banco'
 *   );
 */
class DoubleEntryService
{
    private int $empresaId;
    private int $creadoPor;
    private array $lineas = [];
    private float $totalDebe = 0.0;
    private float $totalHaber = 0.0;

    public function __construct(int $empresaId, int $creadoPor)
    {
        $this->empresaId = $empresaId;
        $this->creadoPor = $creadoPor;
    }

    /**
     * Añade una línea al asiento.
     *
     * @param int $cuentaId ID de la cuenta contable
     * @param float $debe Importe en el debe (0 si no aplica)
     * @param float $haber Importe en el haber (0 si no aplica)
     * @param string|null $descripcion Detalle de la línea
     * @param string|null $referencia Nº factura, albarán, etc.
     * @throws \InvalidArgumentException Si debe y haber son ambos 0 o ambos > 0
     */
    public function addLinea(
        int $cuentaId,
        float $debe,
        float $haber,
        ?string $descripcion = null,
        ?string $referencia = null
    ): void {
        // Validar que solo uno de debe/haber tiene valor
        if ($debe > 0 && $haber > 0) {
            throw new \InvalidArgumentException(
                "Una línea no puede tener importe en debe y haber simultáneamente"
            );
        }
        if ($debe <= 0 && $haber <= 0) {
            throw new \InvalidArgumentException(
                "Una línea debe tener importe en debe o en haber"
            );
        }

        // Validar importes positivos
        if ($debe < 0 || $haber < 0) {
            throw new \InvalidArgumentException("Los importes no pueden ser negativos");
        }

        $this->lineas[] = [
            'cuenta_id' => $cuentaId,
            'debe' => $debe,
            'haber' => $haber,
            'descripcion' => $descripcion,
            'referencia' => $referencia
        ];

        $this->totalDebe += $debe;
        $this->totalHaber += $haber;
    }

    /**
     * Guarda el asiento en la base de datos con transacción ACID.
     *
     * @param string $fecha Fecha del asiento (YYYY-MM-DD)
     * @param string $descripcion Descripción general del asiento
     * @param string $tipo Tipo de asiento
     * @param int|null $facturaId Si proviene de una factura
     * @return int ID del asiento creado
     * @throws \InvalidArgumentException Si la partida doble no cuadra
     * @throws \Exception Si la transacción falla
     */
    public function save(
        string $fecha,
        string $descripcion,
        string $tipo = 'ordinario',
        ?int $facturaId = null
    ): int {
        if (empty($this->lineas)) {
            throw new \InvalidArgumentException("El asiento debe tener al menos una línea");
        }

        // Validación de partida doble: debe == haber
        $diferencia = round($this->totalDebe - $this->totalHaber, 2);
        if ($diferencia !== 0.0) {
            throw new \InvalidArgumentException(
                sprintf(
                    "La partida doble no cuadra. Debe: %.2f | Haber: %.2f | Diferencia: %.2f",
                    $this->totalDebe,
                    $this->totalHaber,
                    $diferencia
                )
            );
        }

        // Ejercicio fiscal basado en la fecha
        $ejercicioFiscal = (int) date('Y', strtotime($fecha));

        return Database::transaction(function (PDO $db) use (
            $fecha, $descripcion, $tipo, $facturaId, $ejercicioFiscal
        ): int {
            // Paso 1: Obtener siguiente número secuencial para este ejercicio
            $stmt = $db->prepare('
                SELECT COALESCE(MAX(numero), 0) + 1 as siguiente
                FROM asientos_contables
                WHERE empresa_id = :empresa_id AND ejercicio_fiscal = :ejercicio
                FOR UPDATE
            ');
            $stmt->execute([
                'empresa_id' => $this->empresaId,
                'ejercicio' => $ejercicioFiscal
            ]);
            $numero = (int) $stmt->fetch()['siguiente'];

            // Paso 2: Crear la cabecera del asiento
            $stmt = $db->prepare('
                INSERT INTO asientos_contables
                    (empresa_id, numero, fecha, descripcion, tipo, ejercicio_fiscal,
                     total_debe, total_haber, factura_id, creado_por)
                VALUES
                    (:empresa_id, :numero, :fecha, :descripcion, :tipo, :ejercicio,
                     :total_debe, :total_haber, :factura_id, :creado_por)
            ');
            $stmt->execute([
                'empresa_id' => $this->empresaId,
                'numero' => $numero,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'tipo' => $tipo,
                'ejercicio' => $ejercicioFiscal,
                'total_debe' => $this->totalDebe,
                'total_haber' => $this->totalHaber,
                'factura_id' => $facturaId,
                'creado_por' => $this->creadoPor
            ]);

            $asientoId = (int) $db->lastInsertId();

            // Paso 3: Insertar cada línea del asiento
            $stmtLinea = $db->prepare('
                INSERT INTO lineas_asiento
                    (asiento_id, cuenta_id, debe, haber, descripcion, referencia)
                VALUES
                    (:asiento_id, :cuenta_id, :debe, :haber, :descripcion, :referencia)
            ');

            foreach ($this->lineas as $linea) {
                $stmtLinea->execute([
                    'asiento_id' => $asientoId,
                    'cuenta_id' => $linea['cuenta_id'],
                    'debe' => $linea['debe'],
                    'haber' => $linea['haber'],
                    'descripcion' => $linea['descripcion'],
                    'referencia' => $linea['referencia']
                ]);

                // Paso 4: Actualizar el saldo de la cuenta afectada
                // Los saldos se actualizan según el tipo de cuenta:
                //   Activos y Gastos: Debe aumenta saldo
                //   Pasivos, PN e Ingresos: Haber aumenta saldo (saldo negativo en DB)
                $stmtTipo = $db->prepare('
                    SELECT tipo FROM plan_cuentas WHERE id = :id AND empresa_id = :empresa
                ');
                $stmtTipo->execute(['id' => $linea['cuenta_id'], 'empresa' => $this->empresaId]);
                $tipoCuenta = $stmtTipo->fetch()['tipo'];

                $ajusteSaldo = 0.0;
                if (in_array($tipoCuenta, ['activo', 'gasto'])) {
                    $ajusteSaldo = $linea['debe'] - $linea['haber'];
                } else {
                    $ajusteSaldo = $linea['haber'] - $linea['debe'];
                }

                $stmtUpdate = $db->prepare('
                    UPDATE plan_cuentas
                    SET saldo_actual = saldo_actual + :ajuste
                    WHERE id = :cuenta_id
                ');
                $stmtUpdate->execute([
                    'ajuste' => $ajusteSaldo,
                    'cuenta_id' => $linea['cuenta_id']
                ]);
            }

            // Si todo va bien, el commit se hace automáticamente en Database::transaction()
            return $asientoId;
        });
    }

    /**
     * Obtiene el total del debe acumulado.
     */
    public function getTotalDebe(): float
    {
        return $this->totalDebe;
    }

    /**
     * Obtiene el total del haber acumulado.
     */
    public function getTotalHaber(): float
    {
        return $this->totalHaber;
    }

    /**
     * Número de líneas añadidas.
     */
    public function getNumLineas(): int
    {
        return count($this->lineas);
    }
}
