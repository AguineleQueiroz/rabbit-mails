# Testes — Backend PHP

Framework: [Pest PHP 2.x](https://pestphp.com) — sintaxe expressiva baseada em funções `it()` e `expect()`.

## Estrutura

```
tests/
├── Pest.php                     ← Bootstrap: carrega .env, fecha Mockery após cada teste
├── Unit/
│   ├── RequestTest.php          ← Testa Request sem I/O externo
│   ├── ResponseTest.php         ← Testa Response (JSON, status, unicode)
│   ├── PipelineTest.php         ← Testa cadeia de middlewares com mocks
│   ├── QueueServiceTest.php     ← Testa QueueService com AMQPChannel mockado
│   └── EmailServiceTest.php     ← Testa EmailService (falha de conexão SMTP)
└── Feature/
    ├── EmailRepositoryTest.php  ← CRUD completo com banco real
    ├── EmailControllerTest.php  ← Fluxo HTTP completo (banco real + QueueService mock)
    └── WorkerTest.php           ← Simula processamento: sucesso, retry, dead letter
```

## Executar

```bash
# Todos os testes (requer banco + RabbitMQ rodando)
docker compose run --rm api vendor/bin/pest

# Apenas unitários — sem infraestrutura externa
docker compose run --rm api vendor/bin/pest tests/Unit

# Apenas feature — requer banco PostgreSQL
docker compose run --rm api vendor/bin/pest tests/Feature

# Com relatório de cobertura
docker compose run --rm api vendor/bin/pest --coverage

# Filtrar por nome
docker compose run --rm api vendor/bin/pest --filter "cria e-mail"
```

## Testes Unitários (`tests/Unit/`)

Não precisam de nenhum serviço externo — podem rodar em qualquer ambiente.

### RequestTest / ResponseTest

Testam as classes de valor puro. Criam instâncias diretamente e verificam propriedades:
```php
it('lê campos do body corretamente', function () {
    $req = new Request('POST', '/api/emails', ['recipient' => 'a@b.com'], [], []);
    expect($req->input('recipient'))->toBe('a@b.com');
});
```

### PipelineTest

Usa classes anônimas implementando `MiddlewareInterface` para testar a cadeia sem dependências:
```php
it('middleware pode interromper o pipeline', function () {
    $response = $pipeline->pipe(RequireFieldMiddleware::class)->run($request, $destination);
    expect($response->statusCode)->toBe(400);
});
```

### QueueServiceTest

Usa **Mockery** para substituir `AMQPStreamConnection` e `AMQPChannel`. Verifica que `basic_publish` é chamado com a routing key correta sem abrir conexão real:
```php
$channel->shouldReceive('basic_publish')
    ->once()
    ->withArgs(fn ($msg, $exchange, $key) => $key === 'pending');
```

## Testes de Feature (`tests/Feature/`)

Precisam do PostgreSQL rodando. Cada teste limpa as tabelas em `beforeEach`:
```php
beforeEach(function () {
    $db = Connection::getInstance();
    $db->exec("TRUNCATE TABLE queue_events, emails RESTART IDENTITY CASCADE");
});
```

### EmailRepositoryTest

Testa todas as queries SQL: create, updateStatus, findById, paginate (com e sem filtro), resetForRequeue, getStats, criação de eventos.

### EmailControllerTest

Cria uma subclasse anônima do controller com `QueueService` substituído por um mock que não abre conexão com RabbitMQ:
```php
function makeController(): EmailController
{
    return new class extends EmailController {
        public function __construct()
        {
            $this->emails = new EmailRepository();
            $this->queue  = new class extends QueueService {
                public function __construct() {}
                public function publish(array $payload, string $routingKey = 'pending'): void {}
            };
        }
    };
}
```

### WorkerTest

Extrai a lógica do callback do worker para uma função `runWorkerCallback()` testável isoladamente. Usa `AMQPMessage` mockado com Mockery para simular mensagens sem fila real.

Cobre:
- Sucesso → `status = sent`, evento `sent`
- Falha tentativa 1 → `publish('retry')`, `attempt = 2`
- Falha tentativa 3 → `publish('dead')`, `status = dead`
- TTL progressivo → `retry_ttl = attempt × base`

## Bootstrap — `tests/Pest.php`

```php
// Carrega variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Reseta singleton de conexão e fecha mocks após cada teste
afterEach(function () {
    Connection::reset();
    Mockery::close();
});
```

`safeLoad()` não lança exceção se o `.env` não existir — útil em pipelines CI onde as variáveis vêm de outra fonte.
