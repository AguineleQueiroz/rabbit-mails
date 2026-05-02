# Views e Roteamento — `src/views/` + `src/router/`

Views são as páginas da SPA. Cada view combina composables (para dados) com componentes (para renderização).

## Roteamento — `src/router/index.ts`

```typescript
createRouter({
  history: createWebHistory(),  // URLs limpas: /emails em vez de /#/emails
  routes: [
    { path: '/',          component: DashboardView  },
    { path: '/emails',    component: EmailsView     },
    { path: '/consumers', component: ConsumersView  },
    { path: '/events',    component: EventsView     },
  ],
})
```

`createWebHistory()` usa a History API do browser — sem hash na URL. Requer que o servidor (Nginx ou Vite) redirecione todas as rotas para `index.html` (configurado em ambos).

O router é montado no `main.ts`:
```typescript
createApp(App).use(router).mount('#app')
```

---

## `DashboardView` — `/`

View mais completa. Combina quatro fontes de dados com intervalos diferentes:

```typescript
const { stats }   = useStats()                          // polling 5s
usePolling(fetchChart,     10000)   // gráfico 10s (muda menos)
usePolling(fetchConsumers, 5000)
usePolling(fetchEvents,    5000)
```

**Layout:**
```
[StatsCards — 4 métricas]

[EmailChart (2/3)]  [ConsumerPanel (1/3)]

[EventLog]
```

---

## `EmailsView` — `/emails`

Controla estado de paginação e filtros, delega renderização ao `EmailTable`:

```typescript
const currentPage   = ref(1)
const currentStatus = ref('')

function handlePageChange(page: number)    { currentPage.value = page; load() }
function handleFilterChange(status: string) { currentStatus.value = status; currentPage.value = 1; load() }
async function handleRequeue(id: string)   { await requeue(id); await load() }
```

A view age como **mediador**: recebe eventos do `EmailTable`, atualiza estado local, dispara novo fetch. O `EmailTable` é stateless — recebe os dados e emite eventos.

---

## `ConsumersView` — `/consumers`

View simples — apenas polling + componente:

```typescript
const consumers = ref<Consumer[]>([])
usePolling(async () => {
  const { data } = await dashboardApi.getConsumers()
  consumers.value = data
}, 5000)
```

---

## `EventsView` — `/events`

Igual ao ConsumersView, mas com 100 eventos (vs 20 do dashboard principal).

---

## `App.vue` — Layout Base

```html
<nav>  <!-- Links de navegação com RouterLink + active-class -->
  Dashboard | E-mails | Workers | Eventos
</nav>
<main>
  <RouterView />  <!-- View atual renderizada aqui -->
</main>
```

`active-class="text-blue-600 font-medium"` — destaca o link da página atual automaticamente via Vue Router.

---

## Navegação

```html
<RouterLink to="/emails">E-mails</RouterLink>
```

`RouterLink` é o `<a>` do Vue Router — sem recarregar a página, apenas troca a view renderizada no `<RouterView>`.
