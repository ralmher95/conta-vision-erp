<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

\ContaVision\Core\Config::get('APP_DEBUG');

$app = \Slim\Factory\AppFactory::create();

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', \ContaVision\Core\Config::get('CORS_ORIGIN', '*'))
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->get('/api/health', function ($request, $response) {
    try {
        $db = \ContaVision\Core\Database::getInstance();
        $db->query('SELECT 1');
        $data = ['status' => 'ok', 'database' => 'connected', 'timestamp' => date('c')];
    } catch (\Throwable $e) {
        $data = ['status' => 'degraded', 'database' => 'error', 'error' => $e->getMessage()];
    }
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Auth routes
$app->post('/api/auth/login', [\ContaVision\Modules\Auth\Controller\AuthController::class, 'login']);
$app->get('/api/auth/me', [\ContaVision\Modules\Auth\Controller\AuthController::class, 'me']);
$app->post('/api/auth/logout', [\ContaVision\Modules\Auth\Controller\AuthController::class, 'logout']);

// Accounting routes (protected)
$app->get('/api/asientos', [\ContaVision\Modules\Accounting\Controller\JournalEntryController::class, 'index']);
$app->get('/api/asientos/{id}', [\ContaVision\Modules\Accounting\Controller\JournalEntryController::class, 'show']);
$app->post('/api/asientos', [\ContaVision\Modules\Accounting\Controller\JournalEntryController::class, 'store']);
$app->delete('/api/asientos/{id}', [\ContaVision\Modules\Accounting\Controller\JournalEntryController::class, 'delete']);

// Chart of Accounts routes
$app->get('/api/cuentas', [\ContaVision\Modules\Accounting\Controller\PlanCuentaController::class, 'index']);
$app->get('/api/cuentas/{id}', [\ContaVision\Modules\Accounting\Controller\PlanCuentaController::class, 'show']);
$app->post('/api/cuentas', [\ContaVision\Modules\Accounting\Controller\PlanCuentaController::class, 'store']);
$app->put('/api/cuentas/{id}', [\ContaVision\Modules\Accounting\Controller\PlanCuentaController::class, 'update']);
$app->delete('/api/cuentas/{id}', [\ContaVision\Modules\Accounting\Controller\PlanCuentaController::class, 'delete']);

// Dashboard routes
$app->get('/api/dashboard/kpis', [\ContaVision\Modules\Dashboard\Controller\DashboardController::class, 'kpis']);
$app->get('/api/dashboard/treasury', [\ContaVision\Modules\Dashboard\Controller\TreasuryController::class, 'simulate']);

$app->run();
