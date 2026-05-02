# API — Email Queue System

API HTTP em PHP 8.4 puro, sem framework. Responsável por receber requisições, persistir dados no PostgreSQL e publicar mensagens no RabbitMQ.

## Visão Geral da Arquitetura

```
Browser / Cliente HTTP
        │
        ▼
   [Nginx 1.27]          ← Porta 8000 (externo)
        │  FastCGI (porta 9000)
        ▼
  [PHP-FPM 8.4]
        │
   public/index.php      ← Entry point único
        │
   [Pipeline]            ← CorsMiddleware → JsonMiddleware
        │
   [FastRoute]           ← Despacha para o Controller correto
        │
   [Controller]          ← Orquestra Service + Repository
        │
   [Service / Repository]← Lógica de negócio / SQL
        │
   [PostgreSQL / RabbitMQ / Redis]
```

## Componentes

| Camada | Diretório | Responsabilidade |
|---|---|---|
| Entry point | `public/index.php` | Bootstrap, roteamento, emissão da resposta |
| HTTP | `src/Http/` | Abstrações de Request, Response e Pipeline |
| Middleware | `src/Middleware/` | CORS e Content-Type |
| Services | `src/Services/` | RabbitMQ, SMTP, Redis heartbeat |
| Repositories | `src/Repositories/` | Acesso ao banco de dados |
| Controllers | `src/Controllers/` | Handlers HTTP |
| Consumer | `consumer/worker.php` | Worker de processamento da fila |

## Guia Rápido

```bash
# Instalar dependências
docker compose run --rm api composer install

# Subir a API (junto com Nginx)
docker compose up -d nginx api

# Testar o health check
curl http://localhost:8000/api/health

# Rodar todos os testes
docker compose run --rm api vendor/bin/pest

# Rodar apenas testes unitários (sem banco ou RabbitMQ)
docker compose run --rm api vendor/bin/pest tests/Unit
```

## Documentação por Área

- [Infraestrutura (Nginx + PHP-FPM)](infrastructure.md)
- [Camada HTTP](http-layer.md)
- [Middlewares](middlewares.md)
- [Services](services.md)
- [Repositories](repositories.md)
- [Controllers](controllers.md)
- [Consumer / Worker](worker.md)
- [Banco de Dados](database.md)
- [Testes](testing.md)
