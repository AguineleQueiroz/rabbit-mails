# Componentes — `src/components/`

Componentes visuais puros: recebem dados via `props`, emitem eventos via `emit`. Não fazem chamadas HTTP, não usam stores globais.

## `StatsCards.vue`

Quatro cards de métricas no topo do dashboard.

**Props:**
```typescript
defineProps<{ stats: Stats }>()
```

**Renderiza:**
- "Na Fila" — `stats.queue.pending` + `stats.queue.processing`
- "Enviados hoje" — `stats.emails.sent_today` + `stats.emails.total`
- "Falharam hoje" — `stats.emails.failed_today` + `stats.emails.dead`
- "Workers ativos" — `stats.consumers.active` + `stats.consumers.idle`

Usa grid responsivo: 2 colunas em mobile, 4 em desktop (`grid-cols-2 md:grid-cols-4`).

---

## `EmailTable.vue`

Tabela paginada de e-mails com filtro por status e botão de reprocessamento.

**Props:**
```typescript
defineProps<{
  emails: Email[]
  total: number
  page: number
  totalPages: number
  loading: boolean
}>()
```

**Emits:**
```typescript
emit('page-change', novaPage: number)
emit('filter-change', status: string)
emit('requeue', emailId: string)
```

**Comportamento:**
- Botão "Reprocessar" aparece apenas para `status === 'failed'` ou `status === 'dead'`
- Status é renderizado com badge colorido (mapeamento em `statusColors`)
- Estado `loading` exibe "Carregando..." em vez da tabela
- Paginação desabilita botões no início e no fim da lista

---

## `ConsumerPanel.vue`

Lista de workers com indicador de status e métricas.

**Props:**
```typescript
defineProps<{ consumers: Consumer[] }>()
```

**Indicadores visuais:**
- Ponto verde = `active`
- Ponto amarelo = `idle`
- Ponto vermelho = `stopped`

`relativeTime()` converte o `last_heartbeat` ISO em "5s atrás", "2m atrás" etc.

---

## `EventLog.vue`

Feed de eventos em ordem cronológica inversa (mais recente no topo).

**Props:**
```typescript
defineProps<{ events: QueueEvent[] }>()
```

Cada tipo de evento tem uma cor diferente:
- `sent` → verde
- `failed` → vermelho
- `retried` → laranja
- `dead_lettered` → vermelho escuro
- `published` → azul
- `requeued` → roxo

Lista tem `max-h-96 overflow-y-auto` — não cresce além de uma altura fixa.

---

## `charts/EmailChart.vue`

Gráfico de linhas com envios por hora nas últimas 24h.

**Props:**
```typescript
defineProps<{ data: ChartPoint[] }>()
// ChartPoint: { hour: string, sent: number, failed: number }
```

Usa Chart.js via `vue-chartjs`. O `chartData` é um `computed` que transforma o array de pontos no formato esperado pelo Chart.js:
- Labels: hora formatada (`14:00`, `15:00`...)
- Dataset verde: enviados
- Dataset vermelho: falharam

A renderização é lazy — o componente só aparece na view se `chartData.length > 0`.

---

## Convenções

- Todo componente usa `<script setup lang="ts">` — sem Options API
- Props tipadas com `defineProps<Interface>()` — sem `any`
- Sem chamadas de API diretas — recebem dados já prontos via props
- Tailwind inline — sem CSS externo por componente
