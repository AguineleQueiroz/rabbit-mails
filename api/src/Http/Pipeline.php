<?php

namespace App\Http;

class Pipeline
{
    private array $middlewares = [];

    public function pipe(string ...$middlewares): static
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    public function run(Request $request, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn ($carry, $middleware) => fn (Request $req) => (new $middleware)->handle($req, $carry),
            $destination
        );

        return $pipeline($request);
    }
}
