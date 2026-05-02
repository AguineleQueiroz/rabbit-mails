# Tipos TypeScript — `src/types/index.ts`

Interfaces compartilhadas por toda a aplicação. Refletem exatamente os contratos da API PHP.

## `Email`

```typescript
interface Email {
  id: string
  recipient: string
  subject: string
  body: string
  status: 'pending' | 'queued' | 'processing' | 'sent' | 'failed' | 'dead'
  attempts: number
  error_message: string | null
  queued_at: string | null
  processed_at: string | null
  created_at: string
  updated_at: string
}
```

`status` é uma union type — o TypeScript recusa qualquer string fora desse conjunto. Garante que o código que filtra por status (`status === 'failed'`) está correto em tempo de compilação.

## `Stats`

```typescript
interface Stats {
  queue:     { pending: number; processing: number }
  emails:    { sent_today: number; failed_today: number; dead: number; total: number }
  consumers: { active: number; idle: number }
}
```

Retornado pelo `GET /api/dashboard/stats`. Os cards de `StatsCards.vue` acessam cada propriedade diretamente.

## `Consumer`

```typescript
interface Consumer {
  worker_id: string
  status: 'active' | 'idle' | 'stopped'
  last_heartbeat: string   // ISO 8601
  emails_processed: number
  emails_failed: number
}
```

Dados vindos do Redis via `HeartbeatService::getAll()`. `last_heartbeat` é usado pelo `ConsumerPanel` para calcular "X segundos atrás".

## `QueueEvent`

```typescript
interface QueueEvent {
  id: string
  email_id: string
  event: string           // 'published' | 'processing' | 'sent' | etc.
  worker_id: string | null
  payload: Record<string, unknown>
  created_at: string
  recipient: string       // JOIN de emails.recipient
  subject: string         // JOIN de emails.subject
}
```

`payload` é `Record<string, unknown>` porque o conteúdo varia por tipo de evento — o EventLog exibe mas não precisa tipar cada campo.

## `PaginatedEmails`

```typescript
interface PaginatedEmails {
  data: Email[]
  total: number
  page: number
  per_page: number
  total_pages: number
}
```

Wrapper de paginação retornado pelo `GET /api/emails`. O `EmailTable` usa `total_pages` para calcular os botões de navegação.

## `QueueInfo`

```typescript
interface QueueInfo {
  messages: number
  consumers: number
  message_stats: Record<string, unknown>
}
```

Dados direto da RabbitMQ Management API. `message_stats` inclui taxas de publish/ack/deliver — estrutura variável dependendo do estado da fila, por isso `Record<string, unknown>`.
