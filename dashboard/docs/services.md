# Serviço HTTP — `src/services/api.ts`

Camada única de comunicação com a API PHP. **Nenhum componente ou composable faz chamadas Axios diretamente** — tudo passa por aqui.

## Por Que Centralizar?

- **Mudança de base URL em um só lugar**: trocar de `localhost:8000` para produção é uma linha
- **Interceptors globais**: adicionar autenticação, logging ou retry para todos os requests
- **Testabilidade**: mockar `api.ts` nos testes de composables é simples
- **Consistência**: headers como `Content-Type` são definidos uma vez

## Configuração do Axios

```typescript
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
  headers: { 'Content-Type': 'application/json' },
})
```

`VITE_API_URL` é lida do arquivo `.env` pelo Vite. Em dev, cai para `http://localhost:8000`. Para deploy, setar no ambiente de CI/CD.

## `dashboardApi`

Endpoints de leitura para o dashboard:

```typescript
dashboardApi.getStats()              // GET /api/dashboard/stats
dashboardApi.getChart()              // GET /api/dashboard/chart
dashboardApi.getQueue()              // GET /api/dashboard/queue
dashboardApi.getConsumers()          // GET /api/dashboard/consumers
dashboardApi.getEvents(50)           // GET /api/dashboard/events?limit=50
```

## `emailsApi`

CRUD de e-mails:

```typescript
emailsApi.list({ page: 1, per_page: 20, status: 'failed' })  // GET /api/emails
emailsApi.show('uuid')                                         // GET /api/emails/{id}
emailsApi.create({ recipient, subject, body })                 // POST /api/emails
emailsApi.requeue('uuid')                                      // POST /api/emails/{id}/requeue
```

## Adicionando Autenticação

Para adicionar um token JWT, basta adicionar um interceptor de request:

```typescript
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})
```

## Variável de Ambiente

Criar `dashboard/.env.local` (não commitada):
```env
VITE_API_URL=http://localhost:8000
```
