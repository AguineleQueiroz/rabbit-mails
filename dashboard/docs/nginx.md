# Nginx — Produção do Dashboard

Em desenvolvimento, o Vite dev server (`npm run dev`) é o servidor correto — ele tem HMR (Hot Module Replacement), compilação incremental e é otimizado para ciclo de desenvolvimento rápido.

Para **produção**, o Vite gera arquivos estáticos otimizados em `dist/` que devem ser servidos por um servidor HTTP eficiente. O Nginx é o servidor padrão para isso.

## Por Que Nginx para Produção?

| | Vite dev | Nginx + `dist/` |
|---|---|---|
| HMR | Sim | Não (desnecessário em prod) |
| Assets comprimidos | Não | Sim (gzip automático) |
| Cache de assets | Não | Headers `Cache-Control: immutable` |
| Source maps | Sim | Não (melhor segurança) |
| Performance | Adequado para dev | Alta (serve arquivos estáticos direto) |

## Config — `dashboard/nginx/nginx.conf`

```nginx
server {
    listen 80;
    root /app/dist;
    index index.html;

    # SPA: qualquer rota que não seja arquivo vai para index.html
    # Vue Router cuida do roteamento no browser
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Assets com hash no nome (ex: app.a3f2b1.js) — cache longo
    location ~* \.(js|css|png|jpg|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    gzip on;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;
}
```

**`try_files $uri $uri/ /index.html`** é essencial para SPAs: quando o browser recarrega `/emails`, Nginx não encontra esse arquivo (não existe em disco) e cai para `index.html`. O Vue Router então renderiza a view correta.

**`Cache-Control: immutable`** + `expires 1y`: o Vite gera hashes nos nomes dos assets (`main.a3b4c5.js`). Como o hash muda a cada build diferente, é seguro dizer ao browser que nunca precisa revalidar esses arquivos.

## Build e Deploy

```bash
# 1. Gerar build de produção
docker compose exec dashboard npm run build
# Saída em dashboard/dist/

# 2. Servir com Nginx
# Copiar dist/ para o servidor e usar nginx.conf
```

## Adicionando ao docker-compose

Para testar o Nginx + build de produção localmente:

```yaml
# Adicionar ao docker-compose.yml:
dashboard-prod:
  image: nginx:1.27-alpine
  ports:
    - "8080:80"
  volumes:
    - ./dashboard/dist:/app/dist:ro
    - ./dashboard/nginx/nginx.conf:/etc/nginx/conf.d/default.conf:ro
  profiles:
    - prod  # só sobe com: docker compose --profile prod up
```

```bash
# Build primeiro
docker compose exec dashboard npm run build

# Subir Nginx de produção
docker compose --profile prod up -d dashboard-prod
```
