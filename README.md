# Rabbit Mails

Sistema de envio de e-mails assíncrono com fila RabbitMQ, API em PHP 8.4, dois workers em paralelo e dashboard Vue.js para monitoramento em tempo real.

## Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) 24+
- [Docker Compose](https://docs.docker.com/compose/) v2 (já incluso no Docker Desktop)

Verifique as versões:
```bash
docker --version
docker compose version
```

---

## 1. Clone o repositório

```bash
git clone <url-do-repositorio>
cd async-mails-rabbitmq
```

---

## 2. Configure as variáveis de ambiente

Copie o arquivo de exemplo:
```bash
cp api/.env.example api/.env
```

As variáveis já estão preenchidas para o ambiente Docker local — nenhuma alteração é necessária para rodar localmente.

<details>
<summary>Ver variáveis disponíveis</summary>

```env
DB_HOST=postgres          # Nome do serviço PostgreSQL no Docker
RABBITMQ_HOST=rabbitmq    # Nome do serviço RabbitMQ no Docker
REDIS_HOST=redis
MAIL_HOST=mailpit          # SMTP local (Mailpit)
RETRY_TTL_SECONDS=30       # Base do backoff de retry (tentativa N × 30s)
HEARTBEAT_INTERVAL=5       # Frequência do heartbeat dos workers (segundos)
```
</details>

---

## 3. Suba a infraestrutura

```bash
docker compose up -d
```

Na primeira execução o Docker irá:
1. Baixar as imagens (`nginx`, `postgres`, `rabbitmq`, `redis`, `mailpit`, `node`)
2. Construir as imagens da `api` e do `dashboard`
3. Instalar dependências PHP (`composer install`) e Node (`npm install`)
4. Executar as migrations SQL automaticamente no PostgreSQL

Aguarde todos os serviços ficarem prontos (~60 segundos na primeira vez):
```bash
docker compose ps
```

Todos os serviços devem aparecer como `running`. Os serviços `api` e `consumer` aguardam os healthchecks do PostgreSQL e do RabbitMQ antes de subir.

---

## 4. Verifique se tudo está funcionando

**API:**
```bash
curl http://localhost:8000/api/health
# {"status":"ok"}
```

**Banco de dados:**
```bash
docker compose exec postgres psql -U user -d emailqueue -c "\dt"
# Deve listar: emails, queue_events, consumers_log
```

**RabbitMQ:**

Abra http://localhost:15672 no browser (login: `guest` / `guest`).
Vá em *Queues* — você deve ver `emails.pending`, `emails.retry` e `emails.dead`.

---

## 5. Envie um e-mail de teste

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

---

## 6. Acompanhe o processamento

**Mailpit** — visualize o e-mail recebido:
```
http://localhost:8025
```

**Logs dos workers em tempo real:**
```bash
docker compose logs -f consumer
```

**Status do e-mail pelo ID retornado no passo 5:**
```bash
curl http://localhost:8000/api/emails/<id-retornado>
```

O campo `status` deve evoluir de `queued` → `processing` → `sent`.

---

## 7. Acesse o dashboard

```
http://localhost:5173
```

O dashboard atualiza automaticamente a cada 5 segundos e mostra:
- **/** — métricas gerais, gráfico de envios por hora, workers e eventos recentes
- **/emails** — tabela paginada com filtro por status e botão de reprocessamento
- **/consumers** — status dos workers ativos (heartbeat Redis)
- **/events** — log completo de eventos da fila

---

## 8. Execute os testes

### Testes PHP (Pest)

Os testes ficam em `api/tests/` e estão divididos em duas suítes:

| Suíte | Diretório | Dependências externas |
|---|---|---|
| Unit | `tests/Unit/` | Nenhuma — usa mocks |
| Feature | `tests/Feature/` | PostgreSQL (banco real) |

**Todos os testes de uma vez** (requer `docker compose up -d` com PostgreSQL e RabbitMQ rodando):
```bash
docker compose run --rm api vendor/bin/pest
```

**Apenas testes unitários** (não precisam de banco — útil durante desenvolvimento):
```bash
docker compose run --rm api vendor/bin/pest tests/Unit
```

**Apenas testes de feature** (disparam contra o banco de dados real):
```bash
docker compose run --rm api vendor/bin/pest tests/Feature
```

**Um único teste pelo nome** (filtro por substring do título):
```bash
docker compose run --rm api vendor/bin/pest --filter "cria e-mail"
```

**Com relatório de cobertura:**
```bash
docker compose run --rm api vendor/bin/pest --coverage
```

#### O que cada suíte cobre

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

> **Nota:** O teste `EmailServiceTest` gera um `WARN` porque `fsockopen()` é bloqueado no
> ambiente de CI sem SMTP disponível. Isso é esperado — o comportamento de erro está coberto.

### Testes do dashboard (Vitest)

Com os serviços rodando:
```bash
docker compose exec dashboard npm run test
```

Em modo watch (durante desenvolvimento):
```bash
docker compose exec dashboard npm run test -- --watch
```

```
dashboard/tests/
├── StatsCards.spec.ts    — renderiza os 4 cards com valores corretos
├── EmailTable.spec.ts    — filtros, paginação e evento de requeue
└── useStats.spec.ts      — polling chama a API e atualiza o ref
```

---

## Serviços e Portas

| Serviço | URL / Porta | Credenciais |
|---|---|---|
| API (via Nginx) | http://localhost:8000 | — |
| Dashboard Vue | http://localhost:5173 | — |
| RabbitMQ Management | http://localhost:15672 | `guest` / `guest` |
| Mailpit (e-mails capturados) | http://localhost:8025 | — |
| PostgreSQL | `localhost:5432` | `user` / `secret` |
| Redis | `localhost:6379` | — |

---

## Operações Comuns

```bash
# Parar todos os serviços (dados preservados)
docker compose stop

# Subir novamente
docker compose up -d

# Escalar para mais workers
docker compose up -d --scale consumer=4

# Ver logs de um serviço específico
docker compose logs -f nginx
docker compose logs -f api
docker compose logs -f consumer

# Acessar o banco interativamente
docker compose exec postgres psql -U user -d emailqueue

# Verificar workers registrados no Redis
docker compose exec redis redis-cli keys 'consumer:*'

# Reconstruir imagens após mudança no Dockerfile ou composer.json
docker compose build api
docker compose up -d

# Destruir tudo incluindo volumes (dados serão perdidos)
docker compose down -v
```

---

## Estrutura do Projeto

```
.
├── api/                    PHP 8.4-FPM — API e workers
│   ├── consumer/           Worker de processamento da fila
│   ├── database/           Migrations SQL
│   ├── docs/               Documentação técnica da API
│   ├── nginx/              Configuração do Nginx
│   ├── public/             Entry point HTTP (index.php)
│   ├── src/                Código-fonte PHP
│   │   ├── Controllers/
│   │   ├── Http/
│   │   ├── Middleware/
│   │   ├── Repositories/
│   │   └── Services/
│   └── tests/              Testes Pest (Unit + Feature)
│
├── dashboard/              Vue 3 + TypeScript — Interface web
│   ├── docs/               Documentação técnica do dashboard
│   ├── nginx/              Configuração Nginx para produção
│   ├── src/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── services/
│   │   ├── types/
│   │   └── views/
│   └── tests/              Testes Vitest
│
├── docker-compose.yml
```

---

## Documentação Técnica

- [`api/docs/`](api/docs/README.md) — infraestrutura, camada HTTP, services, repositories, worker, banco e testes
- [`dashboard/docs/`](dashboard/docs/README.md) — tipos, composables, componentes, views, Nginx e testes
