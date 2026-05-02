<?php

use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use App\Database\Connection;

/**
 * Testes de integração com banco real.
 * Requer PostgreSQL rodando com as variáveis de ambiente configuradas.
 * Execute com: docker compose run --rm api vendor/bin/pest tests/Feature
 */
beforeEach(function () {
    // Limpa tabelas antes de cada teste para isolar estados
    $db = Connection::getInstance();
    $db->exec("TRUNCATE TABLE queue_events, emails RESTART IDENTITY CASCADE");
});

it('cria e-mail com status pending', function () {
    $repo  = new EmailRepository();
    $email = $repo->create([
        'recipient' => 'test@example.com',
        'subject'   => 'Assunto de teste',
        'body'      => '<p>Corpo</p>',
    ]);

    expect($email['recipient'])->toBe('test@example.com')
        ->and($email['status'])->toBe('pending')
        ->and($email['attempts'])->toBe(0)
        ->and($email['id'])->not->toBeNull();
});

it('atualiza status para queued e incrementa attempts', function () {
    $repo  = new EmailRepository();
    $email = $repo->create(['recipient' => 'a@b.com', 'subject' => 'S', 'body' => 'B']);

    $repo->updateStatus($email['id'], 'queued');
    $updated = $repo->findById($email['id']);

    expect($updated['status'])->toBe('queued')
        ->and($updated['attempts'])->toBe(1);
});

it('atualiza status para sent com processed_at preenchido', function () {
    $repo  = new EmailRepository();
    $email = $repo->create(['recipient' => 'a@b.com', 'subject' => 'S', 'body' => 'B']);

    $repo->updateStatus($email['id'], 'sent');
    $updated = $repo->findById($email['id']);

    expect($updated['status'])->toBe('sent')
        ->and($updated['processed_at'])->not->toBeNull();
});

it('atualiza status com mensagem de erro', function () {
    $repo  = new EmailRepository();
    $email = $repo->create(['recipient' => 'a@b.com', 'subject' => 'S', 'body' => 'B']);

    $repo->updateStatus($email['id'], 'failed', 'SMTP connection refused');
    $updated = $repo->findById($email['id']);

    expect($updated['status'])->toBe('failed')
        ->and($updated['error_message'])->toBe('SMTP connection refused');
});

it('retorna null para id inexistente', function () {
    $repo   = new EmailRepository();
    $result = $repo->findById('00000000-0000-0000-0000-000000000000');

    expect($result)->toBeNull();
});

it('pagina e-mails corretamente', function () {
    $repo = new EmailRepository();

    for ($i = 1; $i <= 5; $i++) {
        $repo->create(['recipient' => "user{$i}@test.com", 'subject' => "S{$i}", 'body' => 'B']);
    }

    $page1 = $repo->paginate('', 1, 3);
    $page2 = $repo->paginate('', 2, 3);

    expect($page1['total'])->toBe(5)
        ->and($page1['total_pages'])->toBe(2)
        ->and(count($page1['data']))->toBe(3)
        ->and(count($page2['data']))->toBe(2);
});

it('filtra paginação por status', function () {
    $repo = new EmailRepository();
    $e1   = $repo->create(['recipient' => 'a@test.com', 'subject' => 'S', 'body' => 'B']);
    $e2   = $repo->create(['recipient' => 'b@test.com', 'subject' => 'S', 'body' => 'B']);

    $repo->updateStatus($e1['id'], 'sent');

    $sent = $repo->paginate('sent');
    expect($sent['total'])->toBe(1);
});

it('reseta e-mail para requeue zerado attempts', function () {
    $repo  = new EmailRepository();
    $email = $repo->create(['recipient' => 'a@b.com', 'subject' => 'S', 'body' => 'B']);

    $repo->updateStatus($email['id'], 'failed', 'erro');
    $repo->updateStatus($email['id'], 'failed', 'erro');
    $repo->resetForRequeue($email['id']);

    $updated = $repo->findById($email['id']);

    expect($updated['status'])->toBe('pending')
        ->and($updated['attempts'])->toBe(0)
        ->and($updated['error_message'])->toBeNull();
});

it('getStats retorna contagens corretas', function () {
    $repo = new EmailRepository();
    $e1   = $repo->create(['recipient' => 'a@test.com', 'subject' => 'S', 'body' => 'B']);
    $e2   = $repo->create(['recipient' => 'b@test.com', 'subject' => 'S', 'body' => 'B']);

    $repo->updateStatus($e1['id'], 'queued');
    $repo->updateStatus($e2['id'], 'sent');

    $stats = $repo->getStats();

    expect((int)$stats['queued'])->toBe(1)
        ->and((int)$stats['sent_today'])->toBe(1)
        ->and((int)$stats['total'])->toBeGreaterThanOrEqual(2);
});

it('cria evento e retorna na listagem', function () {
    $emailRepo = new EmailRepository();
    $eventRepo = new EventRepository();

    $email = $emailRepo->create(['recipient' => 'a@b.com', 'subject' => 'S', 'body' => 'B']);
    $eventRepo->create($email['id'], 'published');
    $eventRepo->create($email['id'], 'processing', 'worker-1');

    $events = $eventRepo->latest(10);

    expect(count($events))->toBe(2)
        ->and($events[0]['event'])->toBe('processing')
        ->and($events[0]['recipient'])->toBe('a@b.com');
});
