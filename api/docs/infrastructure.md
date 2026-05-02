# Infraestrutura — Nginx + PHP-FPM

## Por que Nginx + PHP-FPM em vez de `php -S`?

O servidor embutido do PHP (`php -S`) é mono-thread e foi projetado exclusivamente para desenvolvimento local — não suporta múltiplas conexões simultâneas de forma eficiente, não tem configuração de timeout por tipo de rota e não comprime respostas. Nginx + PHP-FPM é o padrão de produção:

| Característica | `php -S` | Nginx + PHP-FPM |
|---|---|---|
| Concorrência | 1 requisição por vez | Pool de workers configurável |
| Compressão gzip | Não | Sim (configurado para JSON) |
| Timeout por rota | Não | Sim (`fastcgi_read_timeout`) |
| Logs estruturados | Não | `access.log` + `error.log` |
| Proteção de arquivos | Não | Bloqueia `.env`, `.git`, etc. |

## Como Funciona

```
Nginx (porta 80 interna / 8000 externa)
  │
  ├── Requisição para arquivo estático (js, css...)
  │     └── Nginx serve diretamente de /var/www/api/public
  │
  └── Requisição para *.php
        └── FastCGI → PHP-FPM (porta 9000) → executa o script
```

O Nginx e o PHP-FPM **são containers separados** no Docker Compose. Eles se comunicam pela rede interna do Docker — o Nginx envia requisições FastCGI para `api:9000`.

## Serviços no Docker Compose

### `nginx`
```yaml
image: nginx:1.27-alpine
ports:
  - "8000:80"              # Porta pública
volumes:
  - ./api:/var/www/api:ro  # Código fonte (read-only — Nginx só serve estático)
  - ./api/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
depends_on:
  - api
```

### `api` (PHP-FPM)
```yaml
build: ./api/Dockerfile    # FROM php:8.4-fpm
# Não expõe porta — só o Nginx acessa via rede interna Docker
env_file: ./api/.env
```

### `consumer`
Usa o mesmo `Dockerfile` do `api` (mesma imagem `php:8.4-fpm`), mas sobrescreve o `CMD` para executar o worker CLI:
```yaml
command: php consumer/worker.php
```
A imagem `php:8.4-fpm` inclui PHP CLI, então o worker funciona normalmente.

## Arquivo de Configuração Nginx

`api/nginx/default.conf` — pontos importantes:

```nginx
# Raiz aponta para public/ (onde fica o index.php)
root /var/www/api/public;

# Front Controller: tudo passa pelo index.php
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# FastCGI para PHP-FPM
location ~ \.php$ {
    fastcgi_pass api:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_read_timeout 60s;  # Tempo para processar publicações na fila
}

# Segurança: bloqueia .env, .git, etc.
location ~ /\. {
    deny all;
}
```

## Dockerfile da API

```dockerfile
FROM php:8.4-fpm

RUN docker-php-ext-install pdo pdo_pgsql sockets pcntl
RUN pecl install amqp redis && docker-php-ext-enable amqp redis

CMD ["php-fpm"]  # Inicia o pool FastCGI na porta 9000
```

Extensões instaladas:
- `pdo` + `pdo_pgsql` — conexão com PostgreSQL
- `amqp` — cliente nativo para RabbitMQ (via PECL)
- `redis` — cliente Redis para heartbeat
- `sockets` — necessário para `php-amqplib`
- `pcntl` — controle de processos (signal handlers no worker)
