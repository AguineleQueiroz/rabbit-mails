<?php

use App\Http\Request;

it('lê campos do body corretamente', function () {
    $req = new Request('POST', '/api/emails', ['recipient' => 'a@b.com'], [], []);

    expect($req->input('recipient'))->toBe('a@b.com')
        ->and($req->input('ausente', 'default'))->toBe('default');
});

it('lê query params corretamente', function () {
    $req = new Request('GET', '/api/emails', [], ['page' => '2', 'status' => 'sent'], []);

    expect($req->query('page'))->toBe('2')
        ->and($req->query('naoexiste', '1'))->toBe('1');
});

it('expõe method e uri como readonly', function () {
    $req = new Request('DELETE', '/api/emails/123', [], [], []);

    expect($req->method)->toBe('DELETE')
        ->and($req->uri)->toBe('/api/emails/123');
});
