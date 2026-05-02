<?php

use App\Http\Response;

it('serializa dados para JSON com status 200', function () {
    $res = Response::json(['status' => 'ok']);

    expect($res->statusCode)->toBe(200)
        ->and($res->body)->toBe('{"status":"ok"}')
        ->and($res->headers['Content-Type'])->toBe('application/json');
});

it('usa status code customizado', function () {
    $res = Response::json(['error' => 'Not found'], 404);

    expect($res->statusCode)->toBe(404);
});

it('preserva acentos sem escapar unicode', function () {
    $res = Response::json(['msg' => 'Enviado com sucesso']);

    expect($res->body)->toContain('Enviado com sucesso');
});
