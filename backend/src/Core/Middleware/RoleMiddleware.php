<?php

declare(strict_types=1);

namespace ContaVision\Core\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de control de acceso por roles y permisos.
 *
 * Se aplica DESPUÉS de AuthMiddleware. Verifica que el usuario
 * tenga el permiso necesario para acceder al recurso.
 *
 * Uso:
 *   $app->get('/api/asientos', $controller, [new RoleMiddleware('accounting.read')]);
 */
class RoleMiddleware implements MiddlewareInterface
{
    private string $permisoRequerido;
    private array $permisosAlternativos;

    /**
     * @param string $permisoRequerido Permiso principal necesario
     * @param string[] $permisosAlternativos Permisos que también otorgan acceso
     */
    public function __construct(string $permisoRequerido, array $permisosAlternativos = [])
    {
        $this->permisoRequerido = $permisoRequerido;
        $this->permisosAlternativos = $permisosAlternativos;
    }

    public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permisos = $request->getAttribute('user_permisos', []);
        $rol = $request->getAttribute('user_rol', '');

        // Admin siempre tiene acceso
        if ($rol === 'admin') {
            return $handler->handle($request);
        }

        // Verificar permiso principal
        if (in_array($this->permisoRequerido, $permisos, true)) {
            return $handler->handle($request);
        }

        // Verificar permisos alternativos
        foreach ($this->permisosAlternativos as $permiso) {
            if (in_array($permiso, $permisos, true)) {
                return $handler->handle($request);
            }
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => 'No tienes permisos para acceder a este recurso',
            'permiso_requerido' => $this->permisoRequerido
        ]));
        return $response->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}
