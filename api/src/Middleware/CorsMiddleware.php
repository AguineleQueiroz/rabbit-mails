<?php

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($request->method === 'OPTIONS') {
            return new Response('', 204);
        }

        return $next($request);
    }
}
