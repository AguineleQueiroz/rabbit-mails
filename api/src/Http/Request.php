<?php

namespace App\Http;

readonly class Request
{
    public function __construct(
        public string $method,
        public string $uri,
        public array  $body,
        public array  $queryParams,
        public array  $headers,
    ) {}

    public static function fromGlobals(): self
    {
        return new self(
            method:      $_SERVER['REQUEST_METHOD'],
            uri:         parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            body:        json_decode(file_get_contents('php://input'), true) ?? [],
            queryParams: $_GET,
            headers:     getallheaders(),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }
}
