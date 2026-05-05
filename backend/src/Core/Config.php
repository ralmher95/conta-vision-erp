<?php

declare(strict_types=1);

namespace ContaVision\Core;

/**
 * Clase para cargar y gestionar variables de entorno desde .env
 *
 * Evita duplicar el código de lectura de .env en múltiples archivos.
 */
class Config
{
    private static array $env = [];
    private static bool $loaded = false;

    /**
     * Carga las variables del archivo .env si no se han cargado ya.
     */
    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenvPath = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($dotenvPath)) {
            // Buscar en directorio backend/
            $dotenvPath = dirname(__DIR__, 3) . '/backend/.env';
        }
        if (!file_exists($dotenvPath)) {
            return;
        }

        $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                self::$env[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtiene el valor de una variable de entorno.
     *
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        return self::$env[$key] ?? $_ENV[$key] ?? $default;
    }

    /**
     * Verifica si APP_DEBUG está activo.
     */
    public static function isDebug(): bool
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }
}
