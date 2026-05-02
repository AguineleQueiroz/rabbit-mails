<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonMiddleware;
use App\Controllers\EmailController;
use App\Controllers\DashboardController;
use FastRoute\RouteCollector;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$request = Request::fromGlobals();

$dispatcher = FastRoute\simpleDispatcher(function (RouteCollector $r) {
    // Emails
    $r->addRoute('POST', '/api/emails',               [EmailController::class, 'store']);
    $r->addRoute('GET',  '/api/emails',               [EmailController::class, 'index']);
    $r->addRoute('GET',  '/api/emails/{id}',          [EmailController::class, 'show']);
    $r->addRoute('POST', '/api/emails/{id}/requeue',  [EmailController::class, 'requeue']);

    // Dashboard
    $r->addRoute('GET', '/api/dashboard/stats',     [DashboardController::class, 'stats']);
    $r->addRoute('GET', '/api/dashboard/chart',     [DashboardController::class, 'chart']);
    $r->addRoute('GET', '/api/dashboard/queue',     [DashboardController::class, 'queue']);
    $r->addRoute('GET', '/api/dashboard/consumers', [DashboardController::class, 'consumers']);
    $r->addRoute('GET', '/api/dashboard/events',    [DashboardController::class, 'events']);

    // Health
    $r->addRoute('GET', '/api/health', fn () => Response::json(['status' => 'ok']));
});

try {
    $pipeline = new Pipeline();
    $response = $pipeline
        ->pipe(CorsMiddleware::class, JsonMiddleware::class)
        ->run($request, function (Request $req) use ($dispatcher) {
            $routeInfo = $dispatcher->dispatch($req->method, $req->uri);

            return match ($routeInfo[0]) {
                FastRoute\Dispatcher::NOT_FOUND          => Response::json(['error' => 'Not found'], 404),
                FastRoute\Dispatcher::METHOD_NOT_ALLOWED => Response::json(['error' => 'Method not allowed'], 405),
                FastRoute\Dispatcher::FOUND              => (function () use ($routeInfo, $req) {
                    [$handler, $vars] = [$routeInfo[1], $routeInfo[2]];

                    if (is_callable($handler)) {
                        return $handler();
                    }

                    [$class, $method] = $handler;
                    return (new $class())->$method($req, $vars);
                })(),
            };
        });
} catch (\Throwable $e) {
    $debug   = $_ENV['APP_DEBUG'] ?? 'false';
    $payload = ['error' => 'Internal server error'];

    if ($debug === 'true') {
        $payload['message'] = $e->getMessage();
        $payload['file']    = $e->getFile() . ':' . $e->getLine();
    }

    error_log('[API] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $response = Response::json($payload, 500);
}

http_response_code($response->statusCode);
foreach ($response->headers as $name => $value) {
    header("$name: $value");
}
echo $response->body;
