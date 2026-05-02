<?php

use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\MiddlewareInterface;

// Middleware de teste que adiciona um header customizado
class AddHeaderMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        return new Response($response->body, $response->statusCode, array_merge(
            $response->headers,
            ['X-Test' => 'applied']
        ));
    }
}

// Middleware que rejeita requests sem um campo específico
class RequireFieldMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->input('required_field')) {
            return Response::json(['error' => 'campo obrigatório'], 400);
        }
        return $next($request);
    }
}

it('executa middlewares em ordem e chega ao destino', function () {
    $pipeline = new Pipeline();
    $request  = new Request('GET', '/', [], [], []);

    $response = $pipeline
        ->pipe(AddHeaderMiddleware::class)
        ->run($request, fn () => Response::json(['ok' => true]));

    expect($response->statusCode)->toBe(200)
        ->and($response->headers['X-Test'])->toBe('applied');
});

it('middleware pode interromper o pipeline sem chamar o destino', function () {
    $pipeline = new Pipeline();
    $request  = new Request('POST', '/', [], [], []);

    $destination = function () {
        throw new \Exception('destino não deveria ser chamado');
    };

    $response = $pipeline
        ->pipe(RequireFieldMiddleware::class)
        ->run($request, $destination);

    expect($response->statusCode)->toBe(400);
});
