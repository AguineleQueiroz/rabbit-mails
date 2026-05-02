# Consumer / Worker

O worker é um processo PHP de longa duração que consome mensagens da fila RabbitMQ e envia os e-mails via SMTP.

## Arquivo: `consumer/worker.php`

## Como Iniciar

```bash
# Via Docker Compose (2 réplicas por padrão)
docker compose up -d consumer

# Escalar para mais workers
docker compose up -d --scale consumer=4

# Ver logs de um worker específico
docker compose logs -f consumer
```

## Fluxo de Processamento

```
Loop: channel->wait()
    │
    ├── Heartbeat (a cada HEARTBEAT_INTERVAL s) → Redis
    │
    └── Mensagem chegou
            │
            ├── updateStatus(emailId, 'processing')
            ├── INSERT queue_event('processing')
            │
            ├── EmailService::send() ──────────────────── sucesso
            │                                                 │
            │                                    updateStatus('sent')
            │                                    INSERT event('sent')
            │                                    message->ack()
            │
            └── EmailService::send() ──────────────────── exceção
                                                              │
                                    attempt < maxAttempts (3)─┤
                                                              │  publish('retry')
                                                              │  updateStatus('failed')
                                                              │  INSERT event('retried')
                                                              │
                                    attempt == maxAttempts ───┤
                                                              │  publish('dead')
                                                              │  updateStatus('dead')
                                                              │  INSERT event('dead_lettered')
                                                              │
                                                        message->ack()
```

## Retry com Backoff Progressivo

O TTL da fila de retry aumenta a cada tentativa:

| Tentativa | Fórmula | TTL (RETRY_TTL_SECONDS=30) |
|---|---|---|
| 1 → retry | `1 × 30s` | 30 segundos |
| 2 → retry | `2 × 30s` | 60 segundos |
| 3 → dead  | — | Permanente |

```php
$ttlMs = $attempt * $retryTtlBase * 1000;
$queue->publish(
    array_merge($payload, ['attempt' => $attempt + 1, 'retry_ttl' => $ttlMs]),
    'retry'
);
```

A fila `emails.retry` tem TTL configurado no RabbitMQ via `x-message-ttl`. Quando expira, o RabbitMQ automaticamente reenvia para `emails.pending`.

## Graceful Shutdown

O worker responde a sinais do sistema operacional:

```php
pcntl_signal(SIGTERM, function () use (&$running) {
    $heartbeat->beat('stopped');
    $running = false;
});
```

Quando `$running = false`, o callback verifica antes de processar:
```php
if (!$running) {
    $message->nack(true); // Recoloca na fila sem processar
    return;
}
```

Isso garante que ao fazer `docker compose stop`, nenhuma mensagem em processamento seja perdida.

## Heartbeat

```php
if (time() - $lastHeartbeat >= $heartbeatInterval) {
    $heartbeat->beat('active', $processed, $failed);
    $lastHeartbeat = time();
}
```

Chamado a cada `HEARTBEAT_INTERVAL` segundos (padrão: 5s). Se o worker travar, o Redis expira o key após 15s e o dashboard remove o worker da lista automaticamente.

## Variáveis de Ambiente Relevantes

| Variável | Padrão | Função |
|---|---|---|
| `WORKER_ID` | `worker-{hostname}` | Identificador único no Redis |
| `HEARTBEAT_INTERVAL` | `5` | Frequência do heartbeat em segundos |
| `RETRY_TTL_SECONDS` | `30` | Base do TTL de retry (multiplicada pela tentativa) |

## Múltiplos Workers

Com `replicas: 2` no docker-compose, dois workers concorrem pela mesma fila. O RabbitMQ distribui as mensagens via round-robin entre os consumidores. O `basic_qos(prefetch=1)` garante que cada worker pega apenas uma mensagem por vez, evitando que um worker rápido "engula" toda a fila.
