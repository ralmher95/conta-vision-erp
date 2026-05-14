<?php

declare(strict_types=1);

namespace ContaVision\Modules\Auth\Controller;

use ContaVision\Core\Database;
use ContaVision\Modules\Auth\Service\JwtService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controlador de autenticación.
 *
 * Endpoints:
 *   POST /api/auth/login    - Iniciar sesión
 *   POST /api/auth/register - Registrar usuario (solo admin)
 *   GET  /api/auth/me       - Obtener datos del usuario actual
 */
class AuthController
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }

    /**
     * POST /api/auth/login
     *
     * Autentica un usuario y devuelve un token JWT.
     *
     * Body JSON:
     * {
     *   "email": "usuario@empresa.com",
     *   "password": "mi_password"
     * }
     *
     * Response 200:
     * {
     *   "token": "eyJ...",
     *   "expires_at": "2025-01-01T20:00:00Z",
     *   "user": { "id": 1, "email": "...", "nombre": "...", "rol": "admin" }
     * }
     */
    public function login(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (empty($body['email']) || empty($body['password'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Email y contraseña son requeridos'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT u.id, u.nombre_completo, u.email, u.password_hash, u.activo,
                   r.slug as rol_slug, r.permisos
            FROM users u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.email = :email
        ');
        $stmt->execute(['email' => $body['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            error_log('[AuthController::login] Intento de login fallido para: ' . $body['email']);
            $response->getBody()->write(json_encode([
                'error' => 'Credenciales inválidas'
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        if (!$user['activo']) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuario desactivado'
            ]));
            return $response->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        // Actualizar last_login
        $stmt = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);

        $token = $this->jwtService->generateToken(
            $user['id'],
            $user['email'],
            $user['rol_slug']
        );

        $responseData = [
            'token' => $token,
            'expires_at' => date('c', $this->jwtService->getTokenExpiration()),
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre_completo'],
                'email' => $user['email'],
                'rol' => $user['rol_slug'],
                'permisos' => json_decode($user['permisos'], true)
            ]
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/auth/me
     *
     * Devuelve los datos del usuario autenticado actual.
     */
    public function me(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT u.id, u.nombre_completo, u.email, u.empresa_id, u.avatar_url, u.activo,
                   r.slug as rol_slug, r.permisos, r.descripcion as rol_descripcion
            FROM users u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.id = :id
        ');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuario no encontrado'
            ]));
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'id' => $user['id'],
            'nombre' => $user['nombre_completo'],
            'email' => $user['email'],
            'empresa_id' => $user['empresa_id'],
            'avatar_url' => $user['avatar_url'],
            'activo' => (bool) $user['activo'],
            'rol' => [
                'slug' => $user['rol_slug'],
                'descripcion' => $user['rol_descripcion'],
                'permisos' => json_decode($user['permisos'], true)
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/auth/register
     *
     * Registra un nuevo usuario. Solo accesible por admin.
     *
     * Body JSON:
     * {
     *   "nombre_completo": "María García",
     *   "email": "maria@empresa.com",
     *   "password": "password_seguro",
     *   "rol_id": 2,
     *   "empresa_id": 1
     * }
     */
    public function register(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (empty($body['nombre_completo']) || empty($body['email']) || empty($body['password'])) {
            $response->getBody()->write(json_encode([
                'error' => 'nombre_completo, email y password son requeridos'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        if (strlen($body['password']) < 8) {
            $response->getBody()->write(json_encode([
                'error' => 'La contraseña debe tener al menos 8 caracteres'
            ]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance();

        // Verificar que el email no existe
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $body['email']]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error' => 'Ya existe un usuario con ese email'
            ]));
            return $response->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }

        $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $db->prepare('
            INSERT INTO users (nombre_completo, email, password_hash, rol_id, empresa_id)
            VALUES (:nombre, :email, :hash, :rol_id, :empresa_id)
        ');
        $stmt->execute([
            'nombre' => $body['nombre_completo'],
            'email' => $body['email'],
            'hash' => $passwordHash,
            'rol_id' => $body['rol_id'] ?? 2,
            'empresa_id' => $body['empresa_id'] ?? null
        ]);

        $userId = (int) $db->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => 'Usuario creado exitosamente',
            'user_id' => $userId
        ]));
        return $response->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/auth/logout
     *
     * Cierra la sesión del usuario. Como usamos JWT (stateless),
     * el cliente debe eliminar el token del almacenamiento local.
     */
    public function logout(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'message' => 'Sesión cerrada exitosamente'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
