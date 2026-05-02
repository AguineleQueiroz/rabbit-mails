<?php

namespace App\Http;

readonly class Response
{
    public function __construct(
        public string $body,
        public int    $statusCode = 200,
        public array  $headers = [],
    ) {}

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            body:       json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            statusCode: $status,
            headers:    ['Content-Type' => 'application/json'],
        );
    }
}
