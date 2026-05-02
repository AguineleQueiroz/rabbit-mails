<?php

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class JsonMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        header('Content-Type: application/json');
        return $next($request);
    }
}
