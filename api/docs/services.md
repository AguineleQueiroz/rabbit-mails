# Services

Três classes com responsabilidades distintas para integração com os sistemas externos.

## QueueService — `src/Services/QueueService.php`

Encapsula toda a lógica do RabbitMQ: setup de exchanges/filas, publicação e consumo.

### Topologia de Filas

```
Exchange "emails" (direct)
    │
    ├── routing key "pending"  →  fila "emails.pending"   ← workers consomem daqui
    │
    ├── routing key "retry"    →  fila "emails.retry"
    │                               TTL progressivo
    │                               ao expirar: DLX → "emails.pending" (reprocessa)
    │
    └── routing key "dead"     →  fila "emails.dead"      ← falhas definitivas

Exchange "emails.dlx" (direct)
    └── usado pela fila retry para devolver mensagens ao pending após o TTL
```

### Configuração da Fila de Retry

```php
new AMQPTable([
    'x-dead-letter-exchange'    => 'emails',
    'x-dead-letter-routing-key' => 'pending',
    'x-message-ttl'             => $retryTtl, // em ms
])
```

A mensagem expira após o TTL e é automaticamente reenviada para `emails.pending` pelo RabbitMQ — sem polling, sem cron.

### `publish(array $payload, string $routingKey = 'pending')`

Publica com `delivery_mode = PERSISTENT`: a mensagem sobrevive a reinicializações do RabbitMQ.

### `consume(callable $callback)`

Configura `basic_qos(prefetch_count=1)`: cada worker processa **uma mensagem por vez** e só pega a próxima após confirmar a atual com `ack()`. Evita que um worker lento acumule mensagens.

---

## EmailService — `src/Services/EmailService.php`

Envia e-mails via SMTP usando socket nativo do PHP — sem biblioteca externa.

### Sequência de Comandos SMTP

```
TCP connect → 220 Welcome
EHLO emailqueue\r\n → 250 OK
MAIL FROM:<...>\r\n  → 250 OK
RCPT TO:<...>\r\n    → 250 OK
DATA\r\n             → 354 Start input
<cabeçalhos + corpo>
.\r\n                → 250 OK (mensagem aceita)
QUIT\r\n             → conexão encerrada
```

O `.` em uma linha isolada é o sinal SMTP de fim do corpo da mensagem.

### Por que socket nativo?

Evita dependência de biblioteca de e-mail (PHPMailer, SwiftMailer) para um caso de uso simples. O Mailpit aceita SMTP sem autenticação e sem TLS, o que torna a implementação direta com `fsockopen`.

### Falha de conexão

```php
$socket = @fsockopen($host, $port, $errno, $errstr, 5);
if (!$socket) {
    throw new \RuntimeException("Falha ao conectar ao SMTP: $errstr ($errno)");
}
```

A exceção é capturada pelo worker, que decide entre retry e dead letter.

---

## HeartbeatService — `src/Services/HeartbeatService.php`

Mantém no Redis um sinal de vida de cada worker para que o dashboard saiba quais estão ativos.

### Como Funciona

```php
// Chamado a cada HEARTBEAT_INTERVAL segundos pelo worker
$this->redis->setex("consumer:{$workerId}", 15, json_encode([
    'worker_id'        => $workerId,
    'status'           => 'active',
    'last_heartbeat'   => date('c'),
    'emails_processed' => $processed,
    'emails_failed'    => $failed,
]));
```

**TTL de 15 segundos**: se o worker travar ou morrer sem enviar `SIGTERM`, o key expira automaticamente e o dashboard para de exibir aquele worker. Não é necessário nenhum processo de limpeza.

### `getAll()`

Usa `KEYS consumer:*` para buscar todos os workers registrados. Em produção com muitos workers, trocar para `SCAN` seria mais eficiente, mas para o escopo do sistema é adequado.

### Ciclo de Vida do Worker

```
start → beat('active')  ← a cada HEARTBEAT_INTERVAL segundos
      → ...
      → SIGTERM → beat('stopped') → exit
```
