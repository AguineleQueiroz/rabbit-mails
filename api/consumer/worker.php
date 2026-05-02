<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\QueueService;
use App\Services\EmailService;
use App\Services\HeartbeatService;
use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use PhpAmqpLib\Message\AMQPMessage;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$workerId  = $_ENV['WORKER_ID'] ?? ('worker-' . gethostname());
$processed = 0;
$failed    = 0;

$queue     = new QueueService();
$mailer    = new EmailService();
$heartbeat = new HeartbeatService($workerId);
$emails    = new EmailRepository();
$events    = new EventRepository();

echo "[{$workerId}] Worker started. Waiting for messages...\n";

$heartbeatInterval = (int)$_ENV['HEARTBEAT_INTERVAL'];
$retryTtlBase      = (int)$_ENV['RETRY_TTL_SECONDS'];
$maxAttempts       = 3;
$lastHeartbeat     = 0;
$running           = true;

declare(ticks=1);

pcntl_signal(SIGTERM, function () use ($workerId, $heartbeat, &$running) {
    echo "[{$workerId}] SIGTERM signal received. Shutting down after current message...\n";
    $heartbeat->beat('stopped');
    $running = false;
});

pcntl_signal(SIGINT, function () use ($workerId, $heartbeat, &$running) {
    echo "[{$workerId}] Interrupted. Shutting down...\n";
    $heartbeat->beat('stopped');
    $running = false;
});

$queue->consume(function (AMQPMessage $message) use (
    $workerId,
    $mailer,
    $heartbeat,
    $emails,
    $events,
    $queue,
    $retryTtlBase,
    $maxAttempts,
    &$processed,
    &$failed,
    &$lastHeartbeat,
    $heartbeatInterval,
    &$running
) {
    if (!$running) {
        /* Put it back in queue before stopping. */
        $message->nack(true);
        return;
    }

    if (time() - $lastHeartbeat >= $heartbeatInterval) {
        $heartbeat->beat('active', $processed, $failed);
        $lastHeartbeat = time();
    }

    $payload = json_decode($message->getBody(), true);
    $emailId = $payload['email_id'];
    $attempt = $payload['attempt'] ?? 1;

    echo "[{$workerId}] Processing email {$emailId} (attempt {$attempt}/{$maxAttempts})\n";

    $emails->updateStatus($emailId, 'processing');
    $events->create($emailId, 'processing', $workerId, $payload);

    try {
        $mailer->send($payload['recipient'], $payload['subject'], $payload['body']);

        $emails->updateStatus($emailId, 'sent');
        $events->create($emailId, 'sent', $workerId);

        $message->ack();
        $processed++;

        echo "[{$workerId}] ✓ Sent to {$payload['recipient']}\n";

    } catch (\Throwable $e) {
        echo "[{$workerId}] ✗ Error: {$e->getMessage()} (attempt {$attempt}/{$maxAttempts})\n";

        if ($attempt < $maxAttempts) {
            /* Progressive backoff: attempt N has a TTL of N * base_ttl seconds */
            $ttlMs = $attempt * $retryTtlBase * 1000;

            $queue->publish(
                array_merge($payload, [
                    'attempt'    => $attempt + 1,
                    'retry_ttl'  => $ttlMs,
                ]),
                'retry'
            );

            $emails->updateStatus($emailId, 'failed', $e->getMessage());
            $events->create($emailId, 'retried', $workerId, [
                'attempt' => $attempt + 1,
                'error'   => $e->getMessage(),
                'ttl_ms'  => $ttlMs,
            ]);

        } else {
            /* All attempts have been exhausted — send to DLQ. */
            $queue->publish($payload, 'dead');
            $emails->updateStatus($emailId, 'dead', $e->getMessage());
            $events->create($emailId, 'dead_lettered', $workerId, [
                'error'   => $e->getMessage(),
                'attempt' => $attempt,
            ]);

            echo "[{$workerId}] ✗ Dead lettered: {$emailId}\n";
        }

        $message->ack();
        $failed++;
    }
});
