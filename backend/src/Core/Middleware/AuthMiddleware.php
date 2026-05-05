<?php

declare(strict_types=1);

namespace ContaVision\Core\Middleware;

use ContaVision\Core\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de autenticación JWT.
 *
 * Valida el token en el header Authorization: Bearer <token>
 * e inyecta los datos del usuario en el request como atributos.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Token de autenticación requerido'
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Extraer el token del header "Bearer <token>"
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Formato de token inválido. Use: Bearer <token>'
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];
        $secret = $_ENV['JWT_SECRET'] ?? 'CHANGE_ME';

        try {
            // Decodificar y validar el token
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Verificar que el usuario existe en la base de datos
            $db = Database::getInstance();
            $stmt = $db->prepare('
                SELECT u.id, u.nombre_completo, u.email, u.activo,
                       r.slug as rol_slug, r.permisos
                FROM users u
                JOIN roles r ON u.rol_id = r.id
                WHERE u.id = :id AND u.activo = 1
            ');
            $stmt->execute(['id' => $decoded->sub]);
            $user = $stmt->fetch();

            if (!$user) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode([
                    'error' => 'Usuario no encontrado o desactivado'
                ]));
                return $response->withStatus(401)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Inyectar datos del usuario en el request para los controllers
            $request = $request->withAttribute('user_id', (int) $decoded->sub);
            $request = $request->withAttribute('user_email', $user['email']);
            $request = $request->withAttribute('user_nombre', $user['nombre_completo']);
            $request = $request->withAttribute('user_rol', $user['rol_slug']);
            $request = $request->withAttribute('user_permisos', json_decode($user['permisos'], true));

            return $handler->handle($request);

        } catch (\Firebase\JWT\ExpiredException $e) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Token expirado'
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Token inválido'
            ]));
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
