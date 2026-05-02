<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class QueueService
{
    protected AMQPStreamConnection $connection;
    protected AMQPChannel $channel;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            $_ENV['RABBITMQ_HOST'],
            (int)$_ENV['RABBITMQ_PORT'],
            $_ENV['RABBITMQ_USER'],
            $_ENV['RABBITMQ_PASSWORD'],
            $_ENV['RABBITMQ_VHOST'],
        );

        $this->channel = $this->connection->channel();
        $this->setup();
    }

    protected function setup(): void
    {
        /* Main Exchange */
        $this->channel->exchange_declare('emails', 'direct', false, true, false);

        /* Dead letter exchange — fate of expired retry messages */
        $this->channel->exchange_declare('emails.dlx', 'direct', false, true, false);

        /* Main queue */
        $this->channel->queue_declare('emails.pending', false, true, false, false);
        $this->channel->queue_bind('emails.pending', 'emails', 'pending');

        /* Retry queue: Progressive TTL + upon expiration, returns to emails.pending via DLX */
        $retryTtl = (int)$_ENV['RETRY_TTL_SECONDS'] * 1000;
        $this->channel->queue_declare('emails.retry', false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange'    => 'emails',
            'x-dead-letter-routing-key' => 'pending',
            'x-message-ttl'             => $retryTtl,
        ]));
        $this->channel->queue_bind('emails.retry', 'emails', 'retry');

        /* Dead Letter Queue — permanent failure after exhausting all attempts. */
        $this->channel->queue_declare('emails.dead', false, true, false, false);
        $this->channel->queue_bind('emails.dead', 'emails', 'dead');
    }

    public function publish(array $payload, string $routingKey = 'pending'): void
    {
        $message = new AMQPMessage(
            json_encode($payload),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type'  => 'application/json',
            ]
        );

        $this->channel->basic_publish($message, 'emails', $routingKey);
    }

    public function consume(callable $callback): void
    {
        /* prefetch_count=1: The worker can only receive another message after confirming the current one. */
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume(
            'emails.pending',
            '',
            false,
            false, // no_ack=false: manual confirmation via ack()
            false,
            false,
            $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        if (isset($this->channel)) {
            $this->channel->close();
        }
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }
}
