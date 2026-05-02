# Controllers

Camada mais fina do sistema — fazem a ponte entre o HTTP e a lógica de negócio. Não contêm SQL, não conhecem o RabbitMQ diretamente, não tomam decisões de negócio.

## Regra de Ouro

> Controller lê do Request → chama Service/Repository → devolve Response.

Nenhuma query SQL, nenhuma conexão com RabbitMQ e nenhuma lógica de retry dentro de um controller.

---

## EmailController — `src/Controllers/EmailController.php`

Gerencia o lifecycle HTTP dos e-mails.

### `POST /api/emails` → `store()`

**Fluxo completo:**

```
1. Valida campos obrigatórios (recipient, subject, body)
2. Valida formato do e-mail (filter_var FILTER_VALIDATE_EMAIL)
3. INSERT no banco (status = pending)
4. Publica no RabbitMQ (routing key = pending)
5. UPDATE status = queued
6. INSERT queue_events (event = published)
7. Retorna 202 Accepted
```

**Por que 202 e não 201?**
`201 Created` indica que o recurso foi criado **e** está pronto para uso. `202 Accepted` indica que o pedido foi aceito para processamento **assíncrono** — o e-mail ainda não foi enviado, apenas enfileirado. É semanticamente correto para sistemas de fila.

**Validações:**
```php
if (!$recipient || !$subject || !$body) → 422
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) → 422
```

### `GET /api/emails` → `index()`

Paginação passada direto ao repository:
```php
$this->emails->paginate(
    status:  $request->query('status', ''),
    page:    (int)$request->query('page', 1),
    perPage: (int)$request->query('per_page', 20),
)
```

### `GET /api/emails/{id}` → `show()`

`findById` retorna `null` se não existir → `404 Not Found`.

### `POST /api/emails/{id}/requeue` → `requeue()`

Reseta o e-mail (`attempts = 0`, `status = pending`) e o publica novamente na fila com `attempt: 1`. Não incrementa tentativas — começa do zero.

---

## DashboardController — `src/Controllers/DashboardController.php`

Endpoints de leitura para o dashboard Vue.js.

### `GET /api/dashboard/stats` → `stats()`

Combina dados do banco e do Redis em uma única resposta:
```json
{
  "queue":     { "pending": ..., "processing": ... },
  "emails":    { "sent_today": ..., "failed_today": ..., "dead": ..., "total": ... },
  "consumers": { "active": ..., "idle": ... }
}
```

Workers ativos são contados via `HeartbeatService::getAll()` filtrando por `status === 'active'`.

### `GET /api/dashboard/queue` → `queue()`

Consulta a **RabbitMQ Management HTTP API** para obter dados em tempo real da fila:

```php
$url = "{$_ENV['RABBITMQ_MANAGEMENT_URL']}/api/queues/%2F/emails.pending";
```

`%2F` é o `/` do vhost padrão codificado para URL. A resposta inclui contagem de mensagens e estatísticas de throughput direto do broker.

### `GET /api/dashboard/events` → `events()`

Retorna os últimos N eventos (`limit` via query string, padrão 50).

### `GET /api/dashboard/consumers` → `consumers()`

Retorna todos os workers ativos via `HeartbeatService::getAll()`.

---

## Roteamento — `public/index.php`

Todas as rotas são declaradas com FastRoute:

```
POST   /api/emails               EmailController::store
GET    /api/emails               EmailController::index
GET    /api/emails/{id}          EmailController::show
POST   /api/emails/{id}/requeue  EmailController::requeue

GET    /api/dashboard/stats      DashboardController::stats
GET    /api/dashboard/chart      DashboardController::chart
GET    /api/dashboard/queue      DashboardController::queue
GET    /api/dashboard/consumers  DashboardController::consumers
GET    /api/dashboard/events     DashboardController::events

GET    /api/health               → {"status":"ok"}
```

Os `$vars` de rotas com parâmetros (ex: `{id}`) são passados como segundo argumento ao método do controller.
