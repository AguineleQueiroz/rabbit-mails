# Rabbit Mails

Sistema de envio de e-mails assíncrono com fila RabbitMQ, API em PHP 8.2, workers paralelos e dashboard Vue.js para monitoramento em tempo real.

---

## Como funciona

Em vez de enviar e-mails direto no request HTTP (lento, bloqueante), o sistema usa uma fila:

1. A **API** recebe o pedido, salva no banco e publica na fila — resposta imediata ao cliente
2. Os **Workers** consomem a fila, enviam o e-mail via SMTP e atualizam o status
3. O **Dashboard** exibe tudo em tempo real: filas, workers, status dos e-mails e eventos

---

## Arquitetura do sistema

```
  Cliente / Aplicação
         │
         │  POST /api/emails
         ▼
  ┌─────────────┐
  │    Nginx    │  :8000
  └──────┬──────┘
         │
         ▼
  ┌─────────────┐     INSERT / UPDATE      ┌──────────────────┐
  │   API PHP   │ ────────────────────────▶│   PostgreSQL     │
  │  (Publisher)│                          │      :5432       │
  └──────┬──────┘                          └──────────────────┘
         │ publish
         ▼
  ┌──────────────────────────────────────┐
  │            RabbitMQ                  │
  │                                      │
  │  ┌──────────────┐                    │
  │  │emails.pending│◀─── TTL expirou ──┐│
  │  └──────┬───────┘                   ││
  │         │ consume                   ││
  │         ▼                           ││
  │  ┌─────────────┐  ┌──────────────┐  ││
  │  │  Worker 1   │  │  Worker 2    │  ││
  │  └──────┬──────┘  └──────┬───────┘  ││
  │         │                │           ││
  │         └───────┬────────┘           ││
  │                 │ falhou < 3x        ││
  │                 ├──────────────────▶ ┤│
  │                 │                   ││  ┌───────────────┐
  │                 │                   └┼─▶│ emails.retry  │
  │                 │ falhou 3x          │  │  (TTL 30–90s) │
  │                 ├────────────────────┼─▶└───────────────┘
  │                 │                   │
  │                 │                   │  ┌───────────────┐
  │                 │                   └─▶│  emails.dead  │
  │                 │                      │     (DLQ)     │
  └─────────────────┼──────────────────────┴───────────────┘
                    │
          ┌─────────┼──────────┐
          │         │          │
          ▼         ▼          ▼
  ┌──────────┐ ┌─────────┐ ┌──────────────────┐
  │ Mailpit  │ │  Redis  │ │   PostgreSQL      │
  │ SMTP     │ │heartbeat│ │ UPDATE status     │
  │ :1025    │ │  :6379  │ │ INSERT events     │
  │ UI :8025 │ └─────────┘ └──────────────────┘
  └──────────┘
         ▲
  ┌──────┴──────┐
  │  Dashboard  │  polling a cada 5s
  │  Vue :5173  │ ─────────────────▶ GET /api/dashboard/*
  └─────────────┘
```

---

## Ciclo de vida de um e-mail

```
  Cliente                 API                RabbitMQ             Worker              SMTP / BD
     │                     │                     │                   │                    │
     │── POST /api/emails ─▶│                     │                   │                    │
     │                     │── INSERT pending ───────────────────────────────────────────▶│ PostgreSQL
     │                     │── publish ─────────▶│                   │                    │
     │                     │── UPDATE queued ────────────────────────────────────────────▶│ PostgreSQL
     │◀── 202 { queued } ──│                     │                   │                    │
     │                     │                     │                   │                    │
     │                     │                     │── deliver ────────▶│                   │
     │                     │                     │                   │── UPDATE process. ─▶│ PostgreSQL
     │                     │                     │                   │                    │
     │                     │                     │              ┌────┴─────────────────┐  │
     │                     │                     │              │  tenta enviar SMTP   │  │
     │                     │                     │              └────┬─────────────────┘  │
     │                     │                     │                   │                    │
     │                     │            SUCESSO  │                   │── EHLO / DATA ────▶│ Mailpit
     │                     │                     │                   │◀── 250 OK ─────────│
     │                     │                     │                   │── UPDATE sent ─────▶│ PostgreSQL
     │                     │                     │                   │                    │
     │                     │       FALHA < 3x    │                   │── UPDATE failed ───▶│ PostgreSQL
     │                     │                     │◀── publish retry ─│                    │
     │                     │                     │  (TTL: N × 30s)   │                    │
     │                     │                     │── TTL expirou ────▶│  (tenta de novo)  │
     │                     │                     │                   │                    │
     │                     │       FALHA = 3x    │                   │── UPDATE dead ─────▶│ PostgreSQL
     │                     │                     │◀── publish dead ──│                    │
     │                     │                     │  (DLQ — parado)   │                    │
```

---

## Stack

| Camada | Tecnologia |
|---|---|
| API / Backend | PHP 8.2 (sem framework) |
| Message Broker | RabbitMQ 3.x |
| Banco de Dados | PostgreSQL 16 |
| Cache / Heartbeat | Redis 7 |
| SMTP local | Mailpit |
| Frontend | Vue 3 + TypeScript + Vite |
| Estilo | Tailwind CSS |
| Gráficos | Chart.js + vue-chartjs |
| Testes backend | Pest PHP |
| Testes frontend | Vitest |
| Containers | Docker + Docker Compose |

---

## Início rápido

### Pré-requisitos

- Docker 24+
- Docker Compose v2

```bash
docker --version
docker compose version
```

### 1. Clone o repositório

```bash
git clone <url-do-repositorio>
cd rabbit-mails
```

### 2. Configure o ambiente

```bash
cp api/.env.example api/.env
```

O arquivo já vem configurado para o ambiente local — nenhuma alteração é necessária para rodar.

### 3. Suba tudo

```bash
docker compose up -d
```

Na primeira execução (~60s), o Docker baixa as imagens, instala dependências PHP e Node e executa as migrations automaticamente.

Verifique se todos os serviços estão prontos:

```bash
docker compose ps
```

### 4. Confirme que está funcionando

```bash
# API
curl http://localhost:8000/api/health
# → {"status":"ok"}

# Banco (deve listar: emails, queue_events, consumers_log)
docker compose exec postgres psql -U user -d emailqueue -c "\dt"
```

Abra o [RabbitMQ Management](http://localhost:15672) (login `guest` / `guest`) → aba *Queues*: você deve ver `emails.pending`, `emails.retry` e `emails.dead`.

---

## Enviando um e-mail

```bash
curl -s -X POST http://localhost:8000/api/emails \
  -H "Content-Type: application/json" \
  -d '{
    "recipient": "voce@exemplo.com",
    "subject": "Teste do sistema",
    "body": "<h1>Olá!</h1><p>E-mail de teste enviado pela fila.</p>"
  }' | python3 -m json.tool
```

Resposta esperada:

```json
{
  "id": "uuid-gerado",
  "status": "queued",
  "message": "E-mail adicionado à fila com sucesso"
}
```

Acompanhe o processamento:

```bash
# Logs dos workers em tempo real
docker compose logs -f consumer

# Status do e-mail pelo ID retornado acima
curl http://localhost:8000/api/emails/<id>
# status evolui: queued → processing → sent
```

Veja o e-mail capturado no **Mailpit**: [http://localhost:8025](http://localhost:8025)

---

## Serviços e portas

| Serviço | URL | Credenciais |
|---|---|---|
| API (via Nginx) | http://localhost:8000 | — |
| Dashboard Vue | http://localhost:5173 | — |
| RabbitMQ Management | http://localhost:15672 | `guest` / `guest` |
| Mailpit (e-mails capturados) | http://localhost:8025 | — |
| PostgreSQL | `localhost:5432` | `user` / `secret` |
| Redis | `localhost:6379` | — |

---

## Dashboard

Acesse [http://localhost:5173](http://localhost:5173) — atualiza automaticamente a cada 5 segundos.

| Rota | O que mostra |
|---|---|
| `/` | Cards de métricas, gráfico de envios por hora e eventos recentes |
| `/emails` | Tabela paginada com filtro por status e botão de reprocessamento |
| `/consumers` | Workers ativos com último heartbeat e contagem de envios |
| `/events` | Log completo de eventos da fila |

---

## API — Referência

### E-mails

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/emails` | Publica e-mail na fila |
| `GET` | `/api/emails` | Lista e-mails (`?status=&page=&per_page=`) |
| `GET` | `/api/emails/{id}` | Detalhe de um e-mail |
| `POST` | `/api/emails/{id}/requeue` | Recoloca e-mail na fila |

### Dashboard

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/dashboard/stats` | Totais: pending, sent, failed, workers ativos |
| `GET` | `/api/dashboard/chart` | Envios por hora (últimas 24h) |
| `GET` | `/api/dashboard/queue` | Status da fila via RabbitMQ Management API |
| `GET` | `/api/dashboard/consumers` | Workers ativos (via Redis) |
| `GET` | `/api/dashboard/events` | Últimos N eventos (`?limit=50`) |
| `GET` | `/api/health` | Healthcheck da API |

---

## Variáveis de ambiente

O arquivo `api/.env.example` contém todos os valores prontos para uso local. As principais:

```env
# Banco
DB_HOST=postgres
DB_DATABASE=emailqueue
DB_USERNAME=user
DB_PASSWORD=secret

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest

# SMTP — Mailpit (local, sem autenticação)
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM=noreply@emailqueue.local
MAIL_USER=
MAIL_PASS=

# Redis (heartbeat dos workers)
REDIS_HOST=redis

# Workers
HEARTBEAT_INTERVAL=5      # segundos entre heartbeats
RETRY_TTL_SECONDS=30      # base do backoff: tentativa N × 30s
```

> Para usar um SMTP real em produção (ex.: Brevo), descomente o bloco correspondente no `.env.example` e preencha as credenciais.

---

## Testes

### Backend (Pest)

```bash
# Todos os testes
docker compose run --rm api vendor/bin/pest

# Apenas unitários (sem banco)
docker compose run --rm api vendor/bin/pest tests/Unit

# Apenas de integração (requer PostgreSQL)
docker compose run --rm api vendor/bin/pest tests/Feature

# Filtrar por nome
docker compose run --rm api vendor/bin/pest --filter "cria e-mail"

# Com cobertura
docker compose run --rm api vendor/bin/pest --coverage
```

**O que está coberto:**

```
tests/Unit/
├── RequestTest.php        — parsing de body, query params e método HTTP
├── ResponseTest.php       — serialização JSON, status codes, unicode
├── PipelineTest.php       — execução e interrupção de middlewares
├── EmailServiceTest.php   — erro ao conectar no SMTP (socket mockado)
└── QueueServiceTest.php   — publicação com routing keys pending/retry/dead

tests/Feature/
├── EmailRepositoryTest.php  — create, updateStatus, findById, paginate, getStats
├── EmailControllerTest.php  — store 202, validação 422, index, show 404, requeue
└── WorkerTest.php           — fluxo de sucesso, retry progressivo, dead letter
```

### Frontend (Vitest)

```bash
# Executar uma vez
docker compose exec dashboard npm run test

# Modo watch (durante desenvolvimento)
docker compose exec dashboard npm run test -- --watch
```

```
dashboard/tests/
├── StatsCards.spec.ts    — renderiza os 4 cards com valores corretos
├── EmailTable.spec.ts    — filtros, paginação e evento de requeue
└── useStats.spec.ts      — polling chama a API e atualiza o ref
```

---

## Regras de negócio

- E-mails nunca são deletados — apenas mudam de status
- Máximo de **3 tentativas** por e-mail
- Retry com backoff: tentativa 1 = 30s · tentativa 2 = 60s · tentativa 3 = 120s
- Worker envia heartbeat a cada 5s para Redis (TTL 15s) — se parar de bater, é marcado como inativo
- Requeue manual (via dashboard) reseta `attempts` para 0
- A API **nunca envia e-mail diretamente** — sempre via fila

---

## Comandos úteis

```bash
# Parar (dados preservados)
docker compose stop

# Subir novamente
docker compose up -d

# Escalar workers
docker compose up -d --scale consumer=4

# Logs de um serviço
docker compose logs -f consumer
docker compose logs -f api

# Acessar o banco interativamente
docker compose exec postgres psql -U user -d emailqueue

# Ver workers registrados no Redis
docker compose exec redis redis-cli keys 'consumer:*'

# Reconstruir imagens após mudança no Dockerfile ou composer.json
docker compose build api && docker compose up -d

# Destruir tudo, incluindo volumes (dados serão perdidos)
docker compose down -v
```

---

## Estrutura do projeto

```
rabbit-mails/
├── api/
│   ├── consumer/           Worker de processamento da fila
│   ├── database/           Migrations SQL (executadas automaticamente)
│   ├── public/             Entry point HTTP (index.php)
│   ├── src/
│   │   ├── Controllers/    EmailController, DashboardController
│   │   ├── Http/           Request, Response, Pipeline (middleware)
│   │   ├── Middleware/     CorsMiddleware, JsonMiddleware
│   │   ├── Repositories/   EmailRepository, EventRepository
│   │   └── Services/       QueueService, EmailService, HeartbeatService
│   └── tests/              Pest — Unit + Feature
│
├── dashboard/
│   ├── src/
│   │   ├── components/     StatsCards, EmailTable, ConsumerPanel, EventLog
│   │   ├── composables/    useStats, useEmails, usePolling
│   │   ├── services/       api.ts (axios)
│   │   ├── types/          index.ts
│   │   └── views/          Dashboard, Emails, Consumers, Events
│   └── tests/              Vitest
│
└── docker-compose.yml
```

---

## Documentação técnica

- [`api/docs/`](api/docs/README.md) — infraestrutura, HTTP, services, repositories, worker e banco
- [`dashboard/docs/`](dashboard/docs/README.md) — tipos, composables, componentes, views e Nginx
