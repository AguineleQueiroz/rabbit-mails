<?php

use App\Services\QueueService;
use App\Services\EmailService;
use App\Services\HeartbeatService;
use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use App\Database\Connection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Testa o fluxo do worker sem RabbitMQ real.
 * Simula o processamento de mensagens com mocks dos services externos.
 */
beforeEach(function () {
    $db = Connection::getInstance();
    $db->exec("TRUNCATE TABLE queue_events, emails RESTART IDENTITY CASCADE");
});

function makeMessage(array $payload): AMQPMessage
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('getBody')->andReturn(json_encode($payload));
    $msg->shouldReceive('ack')->once();
    return $msg;
}

function makeWorkerDependencies(bool $emailSucceeds = true, string $errorMsg = 'SMTP error'): array
{
    $emailService = Mockery::mock(EmailService::class);

    if ($emailSucceeds) {
        $emailService->shouldReceive('send')->once()->andReturn(true);
    } else {
        $emailService->shouldReceive('send')->andThrow(new \RuntimeException($errorMsg));
    }

    $queueService = Mockery::mock(QueueService::class);
    $heartbeat    = Mockery::mock(HeartbeatService::class);
    $heartbeat->shouldReceive('beat')->zeroOrMoreTimes();

    return [
        'emailService' => $emailService,
        'queueService' => $queueService,
        'heartbeat'    => $heartbeat,
        'emails'       => new EmailRepository(),
        'events'       => new EventRepository(),
    ];
}

/**
 * Simula o callback do worker para testar o fluxo sem iniciar o loop consume().
 */
function runWorkerCallback(
    AMQPMessage $message,
    EmailService $mailer,
    QueueService $queue,
    HeartbeatService $heartbeat,
    EmailRepository $emails,
    EventRepository $events,
    string $workerId = 'worker-test',
    int $maxAttempts = 3,
    int $retryTtlBase = 30
): void {
    $payload = json_decode($message->getBody(), true);
    $emailId = $payload['email_id'];
    $attempt = $payload['attempt'] ?? 1;

    $emails->updateStatus($emailId, 'processing');
    $events->create($emailId, 'processing', $workerId, $payload);

    try {
        $mailer->send($payload['recipient'], $payload['subject'], $payload['body']);

        $emails->updateStatus($emailId, 'sent');
        $events->create($emailId, 'sent', $workerId);
        $message->ack();

    } catch (\Throwable $e) {
        if ($attempt < $maxAttempts) {
            $ttlMs = $attempt * $retryTtlBase * 1000;
            $queue->publish(array_merge($payload, ['attempt' => $attempt + 1, 'retry_ttl' => $ttlMs]), 'retry');
            $emails->updateStatus($emailId, 'failed', $e->getMessage());
            $events->create($emailId, 'retried', $workerId, ['attempt' => $attempt + 1, 'error' => $e->getMessage()]);
        } else {
            $queue->publish($payload, 'dead');
            $emails->updateStatus($emailId, 'dead', $e->getMessage());
            $events->create($emailId, 'dead_lettered', $workerId, ['error' => $e->getMessage()]);
        }

        $message->ack();
    }
}

it('processa mensagem com sucesso: status sent e evento sent', function () {
    $emailRepo = new EmailRepository();
    $email     = $emailRepo->create(['recipient' => 'ok@test.com', 'subject' => 'S', 'body' => 'B']);

    $deps    = makeWorkerDependencies(emailSucceeds: true);
    $payload = ['email_id' => $email['id'], 'recipient' => 'ok@test.com', 'subject' => 'S', 'body' => 'B', 'attempt' => 1];
    $message = makeMessage($payload);

    $deps['queueService']->shouldNotReceive('publish');

    runWorkerCallback(
        $message,
        $deps['emailService'],
        $deps['queueService'],
        $deps['heartbeat'],
        $deps['emails'],
        $deps['events'],
    );

    $updated = $emailRepo->findById($email['id']);
    expect($updated['status'])->toBe('sent')
        ->and($updated['processed_at'])->not->toBeNull();

    $events = (new EventRepository())->latest(10);
    $types  = array_column($events, 'event');
    expect($types)->toContain('sent');
});

it('primeira falha: status failed, publica em retry com attempt=2', function () {
    $emailRepo = new EmailRepository();
    $email     = $emailRepo->create(['recipient' => 'fail@test.com', 'subject' => 'S', 'body' => 'B']);

    $deps = makeWorkerDependencies(emailSucceeds: false, errorMsg: 'Connection refused');
    $deps['queueService']->shouldReceive('publish')
        ->once()
        ->withArgs(fn ($p, $key) => $key === 'retry' && $p['attempt'] === 2);

    $payload = ['email_id' => $email['id'], 'recipient' => 'fail@test.com', 'subject' => 'S', 'body' => 'B', 'attempt' => 1];
    $message = makeMessage($payload);

    runWorkerCallback($message, $deps['emailService'], $deps['queueService'], $deps['heartbeat'], $deps['emails'], $deps['events']);

    $updated = $emailRepo->findById($email['id']);
    expect($updated['status'])->toBe('failed')
        ->and($updated['error_message'])->toBe('Connection refused');
});

it('terceira falha: status dead, publica em dead', function () {
    $emailRepo = new EmailRepository();
    $email     = $emailRepo->create(['recipient' => 'dead@test.com', 'subject' => 'S', 'body' => 'B']);

    $deps = makeWorkerDependencies(emailSucceeds: false);
    $deps['queueService']->shouldReceive('publish')
        ->once()
        ->withArgs(fn ($p, $key) => $key === 'dead');

    $payload = ['email_id' => $email['id'], 'recipient' => 'dead@test.com', 'subject' => 'S', 'body' => 'B', 'attempt' => 3];
    $message = makeMessage($payload);

    runWorkerCallback($message, $deps['emailService'], $deps['queueService'], $deps['heartbeat'], $deps['emails'], $deps['events']);

    $updated = $emailRepo->findById($email['id']);
    expect($updated['status'])->toBe('dead');

    $events = (new EventRepository())->latest(5);
    $types  = array_column($events, 'event');
    expect($types)->toContain('dead_lettered');
});

it('segunda falha usa TTL progressivo (attempt 2 → ttl = 2 × base)', function () {
    $emailRepo = new EmailRepository();
    $email     = $emailRepo->create(['recipient' => 'retry2@test.com', 'subject' => 'S', 'body' => 'B']);

    $retryBase = 30;
    $deps      = makeWorkerDependencies(emailSucceeds: false);
    $deps['queueService']->shouldReceive('publish')
        ->once()
        ->withArgs(fn ($p, $key) => $key === 'retry' && $p['retry_ttl'] === ($retryBase * 2 * 1000));

    $payload = ['email_id' => $email['id'], 'recipient' => 'retry2@test.com', 'subject' => 'S', 'body' => 'B', 'attempt' => 2];
    $message = makeMessage($payload);

    runWorkerCallback($message, $deps['emailService'], $deps['queueService'], $deps['heartbeat'], $deps['emails'], $deps['events'], retryTtlBase: $retryBase);
});
