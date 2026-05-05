<?php

declare(strict_types=1);

namespace ContaVision\Core;

use PDO;
use PDOException;

/**
 * Clase de conexión a base de datos con soporte de transacciones ACID.
 *
 * Garantiza integridad contable mediante:
 * - PDO con prepared statements (previene SQL injection)
 * - Transacciones explícitas con BEGIN/COMMIT/ROLLBACK
 * - Emulación de prepared statements DESACTIVADA para usar las nativas de MySQL
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Obtiene una instancia singleton de PDO.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                \ContaVision\Core\Config::get('DB_HOST', 'localhost'),
                \ContaVision\Core\Config::get('DB_PORT', '3306'),
                \ContaVision\Core\Config::get('DB_NAME', 'contavision_erp')
            );

            self::$instance = new PDO(
                $dsn,
                \ContaVision\Core\Config::get('DB_USER', 'root'),
                \ContaVision\Core\Config::get('DB_PASSWORD', ''),
                [
                    // Usar prepared statements nativos de MySQL (no emulados)
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // Lanzar excepciones en caso de error
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // Retornar arrays asociativos por defecto
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Desactivar persistent connections para evitar problemas de locking
                    PDO::ATTR_PERSISTENT => false,
                ]
            );
        }

        return self::$instance;
    }

    /**
     * Ejecuta una consulta dentro de una transacción ACID.
     * Si cualquier operación falla, hace ROLLBACK automático.
     *
     * @param callable $callback Función que recibe el PDO y ejecuta las queries
     * @return mixed Resultado del callback
     * @throws \Exception Si la transacción falla
     */
    public static function transaction(callable $callback): mixed
    {
        $db = self::getInstance();

        try {
            $db->beginTransaction();
            $result = $callback($db);
            $db->commit();

            return $result;
        } catch (\Throwable $e) {
            // Si hay algún error, revertir toda la transacción
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw new \Exception(
                'Transacción fallida - se ha hecho ROLLBACK: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Cierra la conexión (para testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
