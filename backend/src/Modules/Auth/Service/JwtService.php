<?php

declare(strict_types=1);

namespace ContaVision\Modules\Auth\Service;

use Firebase\JWT\JWT;

/**
 * Servicio de generación y validación de tokens JWT.
 */
class JwtService
{
    private string $secret;
    private int $expireHours;

    public function __construct()
    {
        $this->secret = \ContaVision\Core\Config::get('JWT_SECRET', 'CHANGE_ME_TO_A_RANDOM_64_CHAR_STRING');
        $this->expireHours = (int) (\ContaVision\Core\Config::get('JWT_EXPIRE_HOURS', '8'));
    }

    /**
     * Genera un token JWT para un usuario autenticado.
     *
     * @param int $userId ID del usuario
     * @param string $email Email del usuario
     * @param string $rol Rol del usuario
     * @return string Token JWT
     */
    public function generateToken(int $userId, string $email, string $rol): string
    {
        $now = time();

        $payload = [
            'iss' => 'contavision-api',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + ($this->expireHours * 3600),
            'email' => $email,
            'rol' => $rol,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * Decodifica un token JWT y devuelve el payload.
     *
     * @param string $token Token JWT
     * @return object Payload decodificado
     */
    public function decodeToken(string $token): object
    {
        $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->secret, 'HS256'));
        return $decoded;
    }

    /**
     * Obtiene la fecha de expiración del token.
     */
    public function getTokenExpiration(): int
    {
        return time() + ($this->expireHours * 3600);
    }
}
