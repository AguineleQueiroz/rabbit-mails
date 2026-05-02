# Banco de Dados — PostgreSQL 16

## Schema

Arquivo de migração: `database/migrations/001_create_tables.sql`

Executado automaticamente pelo Postgres na primeira inicialização via volume `docker-entrypoint-initdb.d`.

---

## Tabela `emails`

Armazena cada e-mail e seu estado atual no sistema.

```sql
CREATE TABLE emails (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    recipient     VARCHAR(255) NOT NULL,
    subject       VARCHAR(255) NOT NULL,
    body          TEXT NOT NULL,
    status        VARCHAR(50)  NOT NULL DEFAULT 'pending',
    attempts      INT          NOT NULL DEFAULT 0,
    max_attempts  INT          NOT NULL DEFAULT 3,
    error_message TEXT,
    queued_at     TIMESTAMP,
    processed_at  TIMESTAMP,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### Lifecycle de `status`

```
pending → queued → processing → sent
                             → failed (tentativa < 3) → retry → processing → ...
                             → dead   (tentativa = 3)
```

| Status | Significado |
|---|---|
| `pending` | Recebido pela API, ainda não publicado na fila |
| `queued` | Publicado no RabbitMQ, aguardando worker |
| `processing` | Worker está enviando o e-mail agora |
| `sent` | Enviado com sucesso |
| `failed` | Falhou, mas ainda tem tentativas restantes |
| `dead` | Esgotou as tentativas — precisa de intervenção manual |

### Campos de Tempo

- `queued_at`: quando foi inserido (preenchido no INSERT)
- `processed_at`: quando chegou a um estado terminal (`sent`, `failed`, `dead`)
- `created_at` / `updated_at`: auditoria padrão

### Índices

```sql
CREATE INDEX idx_emails_status     ON emails(status);       -- filtro por status
CREATE INDEX idx_emails_created_at ON emails(created_at DESC); -- ordenação
```

---

## Tabela `queue_events`

Log auditável — cada ação do sistema gera um registro aqui. **Nunca é deletado**.

```sql
CREATE TABLE queue_events (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_id   UUID NOT NULL REFERENCES emails(id),
    event      VARCHAR(50) NOT NULL,
    worker_id  VARCHAR(100),
    payload    JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

`payload JSONB` armazena dados contextuais variáveis: para `retried` guarda o número da tentativa e o erro; para `processing` guarda o payload completo da mensagem.

---

## Tabela `consumers_log`

Registro persistente dos workers (complementa o Redis que é volátil).

```sql
CREATE TABLE consumers_log (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    worker_id        VARCHAR(100) NOT NULL UNIQUE,
    status           VARCHAR(50)  NOT NULL DEFAULT 'active',
    last_heartbeat   TIMESTAMP NOT NULL DEFAULT NOW(),
    emails_processed INT NOT NULL DEFAULT 0,
    emails_failed    INT NOT NULL DEFAULT 0,
    started_at       TIMESTAMP NOT NULL DEFAULT NOW()
);
```

---

## Comandos Úteis

```bash
# Acessar o banco
docker compose exec postgres psql -U user -d emailqueue

# Ver tabelas
\dt

# Contar e-mails por status
SELECT status, COUNT(*) FROM emails GROUP BY status;

# Ver últimos eventos
SELECT e.recipient, qe.event, qe.worker_id, qe.created_at
FROM queue_events qe
JOIN emails e ON e.id = qe.email_id
ORDER BY qe.created_at DESC
LIMIT 20;

# Re-executar migrations (dados são perdidos)
docker compose exec postgres psql -U user -d emailqueue \
  -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" \
  -f /docker-entrypoint-initdb.d/001_create_tables.sql
```
