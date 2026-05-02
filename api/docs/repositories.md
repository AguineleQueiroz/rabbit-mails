# Repositories

Camada de acesso ao banco de dados. Controllers e workers nunca escrevem SQL diretamente — toda interação com o PostgreSQL passa pelos repositories.

## Por Que Usar Repositories?

- **SQL centralizado**: mudanças no schema afetam um só lugar
- **Testabilidade**: controllers podem receber um repository fake em testes sem banco real
- **Separação de responsabilidade**: controller não conhece detalhes de persistência

## Connection — `src/Database/Connection.php`

Singleton PDO compartilhado por todos os repositories na mesma requisição:

```php
Connection::getInstance() // Retorna sempre a mesma instância PDO
Connection::reset()       // Usado nos testes para fechar a conexão entre suítes
```

Configurado com `ERRMODE_EXCEPTION` — qualquer erro SQL lança `PDOException` em vez de retornar `false` silenciosamente.

---

## EmailRepository — `src/Repositories/EmailRepository.php`

Gerencia o ciclo de vida completo dos e-mails.

### `create(array $data): array`

```sql
INSERT INTO emails (recipient, subject, body, status, ...)
VALUES (:recipient, :subject, :body, 'pending', ...)
RETURNING *
```

`RETURNING *` evita um segundo SELECT — retorna os dados inseridos (incluindo `id` gerado pelo Postgres) em uma única query.

### `updateStatus(string $id, string $status, ?string $error)`

```sql
UPDATE emails
SET status        = :status,
    error_message = :error,
    attempts      = attempts + 1,
    processed_at  = CASE WHEN :status IN ('sent', 'failed', 'dead') THEN NOW()
                         ELSE processed_at END,
    updated_at    = NOW()
WHERE id = :id
```

`CASE` condicional: `processed_at` só é preenchido quando o e-mail chega a um estado terminal (`sent`, `failed`, `dead`). `attempts` é incrementado a cada chamada — rastreia quantas vezes o worker tentou processar.

### `paginate(string $status, int $page, int $perPage): array`

Paginação com `LIMIT` / `OFFSET` e contagem total separada. O filtro por `status` é opcional — se vazio, busca todos.

### `resetForRequeue(string $id)`

Zera `attempts = 0` e `error_message = NULL`. Chamado quando o usuário clica em "Reprocessar" no dashboard — garante que o e-mail começa com contador limpo.

### `getStats(): array`

```sql
SELECT
    COUNT(*) FILTER (WHERE status = 'queued')                          AS queued,
    COUNT(*) FILTER (WHERE status = 'sent' AND created_at >= NOW() - INTERVAL '1 day') AS sent_today,
    ...
FROM emails
```

`FILTER (WHERE ...)` é uma feature do PostgreSQL 9.4+ que permite múltiplas contagens condicionais em um único `SELECT` — equivalente a vários `COUNT(CASE WHEN ...)` mas mais legível.

### `getChartData(): array`

Agrega envios por hora nas últimas 24h usando `DATE_TRUNC('hour', processed_at)`. Usado pelo gráfico do dashboard.

---

## EventRepository — `src/Repositories/EventRepository.php`

Registro auditável de todos os eventos da fila.

### `create(string $emailId, string $event, ?string $workerId, array $payload)`

Cada ação significativa do sistema registra um evento:

| Evento | Quando |
|---|---|
| `published` | API publicou na fila |
| `processing` | Worker começou a processar |
| `sent` | E-mail enviado com sucesso |
| `failed` | Falha — tentativa < max |
| `retried` | Publicado na fila de retry |
| `dead_lettered` | Tentativas esgotadas |
| `requeued` | Usuário clicou em Reprocessar |

### `latest(int $limit): array`

```sql
SELECT qe.*, e.recipient, e.subject
FROM queue_events qe
JOIN emails e ON e.id = qe.email_id
ORDER BY qe.created_at DESC
LIMIT :limit
```

O `JOIN` inclui `recipient` e `subject` do e-mail para exibição no feed do dashboard sem precisar de uma segunda query.
