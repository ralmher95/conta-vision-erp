<?php

declare(strict_types=1);

/**
 * CONTA-VISIÓN ERP - Entry Point
 *
 * Punto de entrada de la API REST. Configura Slim, middlewares y rutas.
 *
 * Todas las rutas excepto /api/auth/login y /api/auth/register
 * requieren autenticación JWT.
 */

use ContaVision\Core\Database;
use ContaVision\Core\Middleware\AuthMiddleware;
use ContaVision\Core\Middleware\RoleMiddleware;
use ContaVision\Modules\Auth\Controller\AuthController;
use ContaVision\Modules\Accounting\Controller\JournalEntryController;
use ContaVision\Modules\Dashboard\Controller\TreasuryController;
use ContaVision\Modules\Dashboard\Controller\DashboardController;

// Cargar variables de entorno
require_once __DIR__ . '/../vendor/autoload.php';

// Config carga automáticamente el .env la primera vez que se accede
// Solo necesitamos asegurar que la clase esté disponible
\ContaVision\Core\Config::get('APP_DEBUG');

// Inicializar Slim
$app = new \Slim\App(new \Slim\Psr7\Factory\ServerRequestFactory());

// Header CORS en todas las respuestas
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', \ContaVision\Core\Config::get('CORS_ORIGIN', '*'))
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
});

// Manejar preflight OPTIONS
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Health check (sin auth)
$app->get('/api/health', function ($request, $response) {
    try {
        $db = Database::getInstance();
        $db->query('SELECT 1');
        $body = ['status' => 'ok', 'database' => 'connected', 'timestamp' => date('c')];
    } catch (\Throwable $e) {
        $body = ['status' => 'degraded', 'database' => 'error', 'error' => $e->getMessage()];
    }
    $response->getBody()->write(json_encode($body));
    return $response->withHeader('Content-Type', 'application/json');
});

// ==========================================
// RUTAS DE AUTENTICACIÓN (públicas)
// ==========================================
$auth = new AuthController();

$app->post('/api/auth/login', function ($request, $response, $args) use ($auth) {
    return $auth->login($request, $response, $args);
});

$app->post('/api/auth/register', function ($request, $response, $args) use ($auth) {
    return $auth->register($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('admin.manage_users'));

$app->get('/api/auth/me', function ($request, $response, $args) use ($auth) {
    return $auth->me($request, $response, $args);
})->add(new AuthMiddleware());

// ==========================================
// RUTAS DE CONTABILIDAD (protegidas)
// ==========================================
$journal = new JournalEntryController();

$app->get('/api/asientos', function ($request, $response, $args) use ($journal) {
    return $journal->index($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('accounting.read'));

$app->get('/api/asientos/{id}', function ($request, $response, $args) use ($journal) {
    return $journal->show($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('accounting.read'));

$app->post('/api/asientos', function ($request, $response, $args) use ($journal) {
    return $journal->store($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('accounting.write'));

$app->delete('/api/asientos/{id}', function ($request, $response, $args) use ($journal) {
    return $journal->delete($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('accounting.write'));

// ==========================================
// RUTAS DE PROYECCIÓN DE TESORERÍA (protegidas)
// ==========================================
$treasury = new TreasuryController();

$app->post('/api/treasury/simulate', function ($request, $response, $args) use ($treasury) {
    return $treasury->simulate($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('treasury.write', ['treasury.read']));

$app->get('/api/treasury/projections', function ($request, $response, $args) use ($treasury) {
    return $treasury->getProjections($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('treasury.read'));

$app->get('/api/treasury/health', function ($request, $response, $args) use ($treasury) {
    return $treasury->healthCheck($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('treasury.read'));

// ==========================================
// RUTAS DE DASHBOARD (protegidas)
// ==========================================
$dashboard = new DashboardController();

$app->get('/api/dashboard/kpis', function ($request, $response, $args) use ($dashboard) {
    return $dashboard->kpis($request, $response, $args);
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('dashboard.read'));

// ==========================================
// RUTAS DE PLAN DE CUENTAS (protegidas)
// ==========================================
$app->get('/api/cuentas', function ($request, $response, $args) {
    // TODO: implementar CuentasController
    $db = \ContaVision\Core\Database::getInstance();
    $params = $request->getQueryParams();
    $empresaId = (int) ($params['empresa_id'] ?? 0);

    if ($empresaId <= 0) {
        $body = json_encode(['error' => 'empresa_id requerido']);
        $response->getBody()->write($body);
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $db->prepare('
        SELECT id, codigo, descripcion, tipo, nivel, saldo_actual, activa
        FROM plan_cuentas
        WHERE empresa_id = :empresa_id AND activa = 1
        ORDER BY codigo
    ');
    $stmt->execute(['empresa_id' => $empresaId]);
    $cuentas = $stmt->fetchAll();

    $response->getBody()->write(json_encode($cuentas));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware())
  ->add(new RoleMiddleware('accounting.read'));

// ==========================================
// RUTAS PENDIENTES (skeleton para desarrollo futuro)
// ==========================================

// Facturación
// $app->get('/api/facturas', ...)
// $app->post('/api/facturas', ...)
// $app->post('/api/facturas/{id}/pdf', ...)

// Conciliación bancaria
// $app->post('/api/conciliacion/upload', ...)
// $app->get('/api/conciliacion/pendientes', ...)
// $app->post('/api/conciliacion/conciliar', ...)

// ==========================================
// EJECUTAR APLICACIÓN
// ==========================================
$app->run();
