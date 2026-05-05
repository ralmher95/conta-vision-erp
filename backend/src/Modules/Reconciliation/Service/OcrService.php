<?php

declare(strict_types=1);

namespace ContaVision\Modules\Reconciliation\Service;

use ContaVision\Core\Database;
use PDO;

/**
 * Servicio de OCR para procesar extractos bancarios.
 *
 * Flujo completo:
 * 1. Subida de archivo PDF/imagen → guardado en disco
 * 2. Ejecución de Tesseract OCR → extracción de texto crudo
 * 3. Parseo del texto con regex → detección de transacciones
 * 4. Sugerencia de conciliación → matching con asientos existentes
 * 5. Guardado de movimientos en movimientos_bancarios
 *
 * Formato esperado del extracto bancario:
 *   15/01/2025  TRANSFERENCIA RECIBIDA PEREZ SL       1.210,00
 *   14/01/2025  PAGO NOMINA ENERO                     -8.200,00
 *   12/01/2025  COMISION MANTENIMIENTO CUENTA           -12,50
 */
class OcrService
{
    private string $uploadDir;
    private string $tesseractPath;

    public function __construct()
    {
        $this->uploadDir = $_ENV['UPLOAD_DIR'] ?? __DIR__ . '/../../../uploads/extractos';
        $this->tesseractPath = $_ENV['TESSERACT_PATH'] ?? 'tesseract';

        // Crear directorio de uploads si no existe
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Procesa un archivo subido de extracto bancario.
     *
     * @param array $file Archivo de $_FILES['extracto']
     * @param int $cuentaBancariaId ID de la cuenta bancaria asociada
     * @return array Resultado del procesamiento
     */
    public function procesarExtracto(array $file, int $cuentaBancariaId): array
    {
        // Validar archivo
        $error = $this->validarArchivo($file);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        // Guardar archivo
        $filePath = $this->guardarArchivo($file);

        // Registrar en BD
        $db = Database::getInstance();

        try {
            $stmt = $db->prepare('
                INSERT INTO extractos_bancarios
                    (cuenta_bancaria_id, archivo_original, fecha_extracto_inicio,
                     fecha_extracto_fin, estado)
                VALUES
                    (:cuenta_id, :archivo, :fecha_inicio, :fecha_fin, "pendiente_ocr")
            ');
            $stmt->execute([
                'cuenta_id' => $cuentaBancariaId,
                'archivo' => basename($filePath),
                'fecha_inicio' => date('Y-m-01'),
                'fecha_fin' => date('Y-m-t'),
            ]);

            $extractoId = (int) $db->lastInsertId();

            // Ejecutar OCR
            $textoOcr = $this->ejecutarTesseract($filePath);

            if (empty($textoOcr)) {
                $stmt = $db->prepare('
                    UPDATE extractos_bancarios
                    SET estado = "error", errores_ocr = :error
                    WHERE id = :id
                ');
                $stmt->execute([
                    'error' => 'No se pudo extraer texto del archivo',
                    'id' => $extractoId
                ]);

                return ['success' => false, 'error' => 'OCR fallido: no se detectó texto'];
            }

            // Guardar texto OCR
            $stmt = $db->prepare('
                UPDATE extractos_bancarios
                SET texto_ocr = :texto, estado = "procesado"
                WHERE id = :id
            ');
            $stmt->execute([
                'texto' => $textoOcr,
                'id' => $extractoId
            ]);

            // Parsear transacciones del texto
            $transacciones = $this->parsearTransacciones($textoOcr);

            // Guardar movimientos
            $movimientosCreados = 0;
            $stmtMov = $db->prepare('
                INSERT INTO movimientos_bancarios
                    (extracto_id, fecha_operacion, descripcion, importe)
                VALUES
                    (:extracto_id, :fecha, :descripcion, :importe)
            ');

            foreach ($transacciones as $trans) {
                $stmtMov->execute([
                    'extracto_id' => $extractoId,
                    'fecha' => $trans['fecha'],
                    'descripcion' => $trans['descripcion'],
                    'importe' => $trans['importe'],
                ]);
                $movimientosCreados++;
            }

            // Generar sugerencias de conciliación
            $sugerencias = $this->generarSugerencias($extractoId);

            return [
                'success' => true,
                'extracto_id' => $extractoId,
                'texto_extraido' => substr($textoOcr, 0, 200) . '...',
                'transacciones_encontradas' => $movimientosCreados,
                'sugerencias_conciliacion' => $sugerencias,
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error procesando extracto: ' . $e->getMessage()];
        }
    }

    /**
     * Ejecuta Tesseract OCR sobre un archivo.
     *
     * Si el archivo es PDF, primero lo convierte a imagen con Ghostscript.
     */
    private function ejecutarTesseract(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $imagePath = $filePath;

        // Si es PDF, convertir a imagen
        if ($ext === 'pdf') {
            $imagePath = $filePath . '.png';
            $command = sprintf(
                'gs -dNOPAUSE -sDEVICE=pngmono -r300 -sOutputFile=%s %s -c quit 2>/dev/null',
                escapeshellarg($imagePath),
                escapeshellarg($filePath)
            );
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                // Fallback: intentar con pdftoppm (más ligero)
                $imagePath = $filePath . '_ppm';
                $command = sprintf(
                    'pdftoppm -png -r 300 %s %s 2>/dev/null',
                    escapeshellarg($filePath),
                    escapeshellarg($imagePath)
                );
                exec($command);
                $imagePath = $imagePath . '-1.png';
            }
        }

        // Ejecutar Tesseract
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
        $command = sprintf(
            '%s %s %s -l spa+eng --psm 6 2>&1',
            escapeshellarg($this->tesseractPath),
            escapeshellarg($imagePath),
            escapeshellarg($tempFile)
        );

        exec($command, $output, $returnCode);

        $texto = '';
        if (file_exists($tempFile . '.txt')) {
            $texto = file_get_contents($tempFile . '.txt');
            unlink($tempFile . '.txt');
        }
        unlink($tempFile);

        // Limpiar archivos temporales de imagen
        if ($ext === 'pdf' && file_exists($imagePath)) {
            unlink($imagePath);
        }

        return trim($texto);
    }

    /**
     * Parsea transacciones bancarias del texto OCR.
     *
     * Busca patrones de fecha + concepto + importe.
     * Soporta formatos:
     *   DD/MM/AAAA  CONCEPTO                    1.234,56
     *   DD/MM/AAAA  CONCEPTO                    -1.234,56
     *   AAAA-MM-DD  CONCEPTO                     1234.56
     */
    private function parsearTransacciones(string $texto): array
    {
        $lineas = explode("\n", $texto);
        $transacciones = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            // Patrón 1: DD/MM/AAAA ... importe
            if (preg_match(
                '/(\d{2}[\/\-]\d{2}[\/\-]\d{4})\s+(.+?)\s+([\-]?[\d\.]+,\d{2})\s*$/',
                $linea,
                $matches
            )) {
                $fecha = $this->parsearFecha($matches[1]);
                $descripcion = trim($matches[2]);
                $importe = $this->parsearImporte($matches[3]);

                if ($fecha && $importe !== null) {
                    $transacciones[] = [
                        'fecha' => $fecha,
                        'descripcion' => $descripcion,
                        'importe' => $importe,
                    ];
                }
                continue;
            }

            // Patrón 2: AAAA-MM-DD ... importe (formato americano)
            if (preg_match(
                '/(\d{4}[\/\-]\d{2}[\/\-]\d{2})\s+(.+?)\s+([\-]?\d+[\.,]\d{2})\s*$/',
                $linea,
                $matches
            )) {
                $fecha = $this->parsearFecha($matches[1]);
                $descripcion = trim($matches[2]);
                $importe = $this->parsearImporte($matches[3]);

                if ($fecha && $importe !== null) {
                    $transacciones[] = [
                        'fecha' => $fecha,
                        'descripcion' => $descripcion,
                        'importe' => $importe,
                    ];
                }
            }
        }

        return $transacciones;
    }

    /**
     * Genera sugerencias de conciliación matching movimientos bancarios
     * con asientos contables existentes por fecha e importe.
     */
    private function generarSugerencias(int $extractoId): array
    {
        $db = Database::getInstance();

        // Obtener movimientos sin conciliar
        $stmt = $db->prepare('
            SELECT mb.id, mb.fecha_operacion, mb.descripcion, mb.importe
            FROM movimientos_bancarios mb
            WHERE mb.extracto_id = :extracto_id AND mb.conciliado = 0
        ');
        $stmt->execute(['extracto_id' => $extractoId]);
        $movimientos = $stmt->fetchAll();

        $sugerencias = [];

        foreach ($movimientos as $mov) {
            // Buscar asientos con fecha cercana (+-3 días) e importe coincidente
            $importeAsiento = $mov['importe'] > 0
                ? ($mov['importe'] - 0) // Si es positivo, buscar en debe
                : ($mov['importe'] + 0); // Si es negativo, buscar en haber

            $stmt = $db->prepare('
                SELECT a.id, a.numero, a.fecha, a.descripcion,
                       la.debe, la.haber, la.descripcion as linea_desc,
                       pc.codigo as cuenta_codigo
                FROM asientos_contables a
                JOIN lineas_asiento la ON a.id = la.asiento_id
                JOIN plan_cuentas pc ON la.cuenta_id = pc.id
                WHERE a.empresa_id = (
                    SELECT cb.empresa_id FROM cuentas_bancarias cb
                    JOIN extractos_bancarios eb ON eb.cuenta_bancaria_id = cb.id
                    WHERE eb.id = :extracto_id
                )
                AND a.fecha BETWEEN DATE_SUB(:fecha, INTERVAL 3 DAY)
                               AND DATE_ADD(:fecha, INTERVAL 3 DAY)
                AND (
                    (la.debe = :importe_pos AND :importe > 0)
                    OR (la.haber = ABS(:importe_neg) AND :importe < 0)
                )
                LIMIT 3
            ');

            $importePos = $mov['importe'] > 0 ? abs($mov['importe']) : 0;
            $importeNeg = $mov['importe'] < 0 ? abs($mov['importe']) : 0;

            $stmt->execute([
                'extracto_id' => $extractoId,
                'fecha' => $mov['fecha_operacion'],
                'importe' => $mov['importe'],
                'importe_pos' => $importePos,
                'importe_neg' => $importeNeg,
            ]);

            $asientosCoincidentes = $stmt->fetchAll();

            if (!empty($asientosCoincidentes)) {
                $sugerencias[] = [
                    'movimiento_id' => $mov['id'],
                    'movimiento' => [
                        'fecha' => $mov['fecha_operacion'],
                        'descripcion' => $mov['descripcion'],
                        'importe' => $mov['importe'],
                    ],
                    'asientos_sugeridos' => $asientosCoincidentes,
                    'confianza' => 'alta',
                ];
            } else {
                // Sin coincidencia exacta → sugerencia baja confianza
                $sugerencias[] = [
                    'movimiento_id' => $mov['id'],
                    'movimiento' => [
                        'fecha' => $mov['fecha_operacion'],
                        'descripcion' => $mov['descripcion'],
                        'importe' => $mov['importe'],
                    ],
                    'asientos_sugeridos' => [],
                    'confianza' => 'sin_coincidencia',
                ];
            }
        }

        return $sugerencias;
    }

    /**
     * Concilia un movimiento bancario con un asiento contable.
     */
    public function conciliar(int $movimientoId, int $asientoId, int $userId): array
    {
        $db = Database::getInstance();

        try {
            Database::transaction(function (PDO $db) use ($movimientoId, $asientoId, $userId) {
                // Obtener empresa del movimiento
                $stmt = $db->prepare('
                    SELECT eb.cuenta_bancaria_id, cb.empresa_id
                    FROM movimientos_bancarios mb
                    JOIN extractos_bancarios eb ON mb.extracto_id = eb.id
                    JOIN cuentas_bancarias cb ON eb.cuenta_bancaria_id = cb.id
                    WHERE mb.id = :mov_id
                ');
                $stmt->execute(['mov_id' => $movimientoId]);
                $info = $stmt->fetch();

                if (!$info) {
                    throw new \Exception('Movimiento no encontrado');
                }

                // Marcar movimiento como conciliado
                $stmt = $db->prepare('
                    UPDATE movimientos_bancarios
                    SET conciliado = 1, asiento_sugerido_id = :asiento_id
                    WHERE id = :mov_id
                ');
                $stmt->execute([
                    'asiento_id' => $asientoId,
                    'mov_id' => $movimientoId,
                ]);

                // Marcar asiento como conciliado
                $stmt = $db->prepare('
                    UPDATE asientos_contables SET conciliado = 1 WHERE id = :asiento_id
                ');
                $stmt->execute(['asiento_id' => $asientoId]);

                // Registrar en conciliaciones
                $stmt = $db->prepare('
                    INSERT INTO conciliaciones
                        (empresa_id, movimiento_bancario_id, asiento_id, conciliado_por)
                    VALUES
                        (:empresa_id, :mov_id, :asiento_id, :user_id)
                ');
                $stmt->execute([
                    'empresa_id' => $info['empresa_id'],
                    'mov_id' => $movimientoId,
                    'asiento_id' => $asientoId,
                    'user_id' => $userId,
                ]);
            });

            return ['success' => true, 'message' => 'Conciliación realizada'];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==========================================
    // MÉTODOS AUXILIARES
    // ==========================================

    private function validarArchivo(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Error al subir el archivo (código: ' . $file['error'] . ')';
        }

        $allowed = ['application/pdf', 'image/png', 'image/jpeg', 'image/tiff'];
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            return 'Formato no válido. Solo se aceptan PDF, PNG, JPG y TIFF.';
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            return 'El archivo no puede superar 10 MB.';
        }

        return null;
    }

    private function guardarArchivo(array $file): string
    {
        $filename = uniqid('extracto_') . '_' . basename($file['name']);
        $destPath = rtrim($this->uploadDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('No se pudo guardar el archivo');
        }

        return $destPath;
    }

    private function parsearFecha(string $fechaStr): ?string
    {
        // Intentar múltiples formatos
        $formatos = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'];

        foreach ($formatos as $formato) {
            $dt = \DateTime::createFromFormat($formato, $fechaStr);
            if ($dt && $dt->format($formato) === $fechaStr) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function parsearImporte(string $importeStr): ?float
    {
        // Quitar puntos de miles y convertir coma decimal a punto
        $limpio = str_replace('.', '', $importeStr);
        $limpio = str_replace(',', '.', $limpio);

        $valor = floatval($limpio);
        return $valor !== 0.0 ? $valor : null;
    }
}
