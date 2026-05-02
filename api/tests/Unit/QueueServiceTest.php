<?php

use PhpAmqpLib\Message\AMQPMessage;

/**
 * Testa a lógica de publicação do QueueService sem conexão real com RabbitMQ.
 * Usamos mocks do canal AMQP para verificar routing keys e serialização.
 */
it('publica mensagem na fila pending com routing key correta', function () {
    $channel = Mockery::mock(\PhpAmqpLib\Channel\AMQPChannel::class);
    $channel->shouldReceive('exchange_declare')->times(2);
    $channel->shouldReceive('queue_declare')->times(3);
    $channel->shouldReceive('queue_bind')->times(3);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $msg, string $exchange, string $routingKey) {
            $payload = json_decode($msg->getBody(), true);
            return $exchange === 'emails'
                && $routingKey === 'pending'
                && $payload['email_id'] === 'uuid-123'
                && $payload['attempt'] === 1;
        });

    $connection = Mockery::mock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);
    $connection->shouldReceive('channel')->andReturn($channel);
    $connection->shouldReceive('close');
    $channel->shouldReceive('close');

    // Injeta conexão mockada via reflexão
    $service = new class($connection) extends \App\Services\QueueService {
        public function __construct(private \PhpAmqpLib\Connection\AMQPStreamConnection $mockConn)
        {
            // Não chama o construtor pai — conexão virá do mock
        }

        public function injectChannel(\PhpAmqpLib\Channel\AMQPChannel $channel): void
        {
            $this->connection = $this->mockConn;
            $this->channel    = $channel;
            $this->setup();
        }
    };

    $service->injectChannel($channel);
    $service->publish(['email_id' => 'uuid-123', 'attempt' => 1], 'pending');
});

it('publica mensagem com routing key retry', function () {
    $channel = Mockery::mock(\PhpAmqpLib\Channel\AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(fn ($msg, $exchange, $key) => $key === 'retry');
    $channel->shouldReceive('close');

    $connection = Mockery::mock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);
    $connection->shouldReceive('close');

    $service = new class($connection) extends \App\Services\QueueService {
        public function __construct(private \PhpAmqpLib\Connection\AMQPStreamConnection $mockConn) {}

        public function injectChannel(\PhpAmqpLib\Channel\AMQPChannel $ch): void
        {
            $this->connection = $this->mockConn;
            $this->channel    = $ch;
        }
    };

    $service->injectChannel($channel);
    $service->publish(['email_id' => 'uuid-456', 'attempt' => 2], 'retry');
});

it('publica mensagem com routing key dead', function () {
    $channel = Mockery::mock(\PhpAmqpLib\Channel\AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(fn ($msg, $exchange, $key) => $key === 'dead');
    $channel->shouldReceive('close');

    $connection = Mockery::mock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);
    $connection->shouldReceive('close');

    $service = new class($connection) extends \App\Services\QueueService {
        public function __construct(private \PhpAmqpLib\Connection\AMQPStreamConnection $mockConn) {}

        public function injectChannel(\PhpAmqpLib\Channel\AMQPChannel $ch): void
        {
            $this->connection = $this->mockConn;
            $this->channel    = $ch;
        }
    };

    $service->injectChannel($channel);
    $service->publish(['email_id' => 'uuid-789', 'attempt' => 3], 'dead');
});
