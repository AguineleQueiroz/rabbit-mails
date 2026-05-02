<?php

use App\Http\Request;
use App\Controllers\EmailController;
use App\Database\Connection;
use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use App\Services\QueueService;

/**
 * Testa o EmailController com banco real e QueueService mockado.
 * O mock do QueueService evita conexão com RabbitMQ nos testes.
 */
beforeEach(function () {
    $db = Connection::getInstance();
    $db->exec("TRUNCATE TABLE queue_events, emails RESTART IDENTITY CASCADE");
});

function makeRequest(string $method, string $uri, array $body = [], array $query = []): Request
{
    return new Request($method, $uri, $body, $query, []);
}

// QueueService sem conexão real — usado como dependência nos testes
function makeMockQueue(): QueueService
{
    return new class extends QueueService {
        public function __construct() {}
        public function publish(array $payload, string $routingKey = 'pending'): void {}
    };
}

function makeController(): EmailController
{
    return new EmailController(
        emails: new EmailRepository(),
        events: new EventRepository(),
        queue:  makeMockQueue(),
    );
}

it('cria e-mail válido e retorna 202 com status queued', function () {
    $controller = makeController();
    $request    = makeRequest('POST', '/api/emails', [
        'recipient' => 'joao@example.com',
        'subject'   => 'Bem-vindo',
        'body'      => '<p>Olá João</p>',
    ]);

    $response = $controller->store($request);
    $data     = json_decode($response->body, true);

    expect($response->statusCode)->toBe(202)
        ->and($data['status'])->toBe('queued')
        ->and($data['id'])->not->toBeNull();
});

it('rejeita request sem recipient com 422', function () {
    $controller = makeController();
    $request    = makeRequest('POST', '/api/emails', [
        'subject' => 'Teste',
        'body'    => 'Corpo',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode)->toBe(422);
});

it('rejeita e-mail com formato inválido com 422', function () {
    $controller = makeController();
    $request    = makeRequest('POST', '/api/emails', [
        'recipient' => 'nao-e-email',
        'subject'   => 'Teste',
        'body'      => 'Corpo',
    ]);

    $response = $controller->store($request);
    $data     = json_decode($response->body, true);

    expect($response->statusCode)->toBe(422)
        ->and($data['error'])->toContain('inválido');
});

it('lista e-mails com paginação', function () {
    $repo = new EmailRepository();
    for ($i = 0; $i < 5; $i++) {
        $repo->create(['recipient' => "u{$i}@test.com", 'subject' => 'S', 'body' => 'B']);
    }

    $controller = makeController();
    $request    = makeRequest('GET', '/api/emails', [], ['page' => '1', 'per_page' => '3']);

    $response = $controller->index($request);
    $data     = json_decode($response->body, true);

    expect($response->statusCode)->toBe(200)
        ->and($data['total'])->toBe(5)
        ->and(count($data['data']))->toBe(3);
});

it('retorna 404 para e-mail inexistente em show', function () {
    $controller = makeController();
    $request    = makeRequest('GET', '/api/emails/00000000-0000-0000-0000-000000000000');

    $response = $controller->show($request, ['id' => '00000000-0000-0000-0000-000000000000']);

    expect($response->statusCode)->toBe(404);
});

it('retorna detalhes do e-mail em show', function () {
    $repo  = new EmailRepository();
    $email = $repo->create(['recipient' => 'a@b.com', 'subject' => 'Subj', 'body' => 'Body']);

    $controller = makeController();
    $request    = makeRequest('GET', "/api/emails/{$email['id']}");

    $response = $controller->show($request, ['id' => $email['id']]);
    $data     = json_decode($response->body, true);

    expect($response->statusCode)->toBe(200)
        ->and($data['recipient'])->toBe('a@b.com');
});

it('requeue reseta attempts e publica novamente', function () {
    $repo  = new EmailRepository();
    $email = $repo->create(['recipient' => 'a@b.com', 'subject' => 'S', 'body' => 'B']);
    $repo->updateStatus($email['id'], 'failed', 'erro');
    $repo->updateStatus($email['id'], 'failed', 'erro');

    $controller = makeController();
    $request    = makeRequest('POST', "/api/emails/{$email['id']}/requeue");

    $response = $controller->requeue($request, ['id' => $email['id']]);

    expect($response->statusCode)->toBe(200);

    $updated = $repo->findById($email['id']);
    expect($updated['status'])->toBe('pending')
        ->and($updated['attempts'])->toBe(0);
});

it('requeue retorna 404 para e-mail inexistente', function () {
    $controller = makeController();
    $request    = makeRequest('POST', '/api/emails/00000000-0000-0000-0000-000000000000/requeue');

    $response = $controller->requeue($request, ['id' => '00000000-0000-0000-0000-000000000000']);

    expect($response->statusCode)->toBe(404);
});
