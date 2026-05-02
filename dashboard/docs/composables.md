# Composables — `src/composables/`

Composables são funções que encapsulam lógica stateful com a Composition API do Vue. Prefixo `use` por convenção. As views usam composables para obter dados; os componentes só recebem props.

## `usePolling` — `usePolling.ts`

Base de todos os outros composables. Executa um callback imediatamente ao montar e repete a cada `intervalMs` milissegundos.

```typescript
export function usePolling(callback: () => void, intervalMs = 5000) {
  let timer: ReturnType<typeof setInterval>

  onMounted(() => {
    callback()                           // Executa imediatamente
    timer = setInterval(callback, intervalMs)  // Agenda repetição
  })

  onUnmounted(() => clearInterval(timer)) // Limpa ao desmontar o componente
}
```

**Por que `onUnmounted` é essencial?**
Sem limpar o interval, ao navegar para outra rota o timer continua rodando em background — acumulando chamadas de API desnecessárias e potencialmente causando erros de "component unmounted".

**Uso:**
```typescript
usePolling(() => fetchStats(), 5000)  // Busca stats a cada 5 segundos
```

---

## `useStats` — `useStats.ts`

Busca e mantém atualizado o objeto `Stats` para o dashboard principal.

```typescript
export function useStats() {
  const stats   = ref<Stats | null>(null)
  const loading = ref(true)
  const error   = ref<string | null>(null)

  async function fetchStats() {
    try {
      const { data } = await dashboardApi.getStats()
      stats.value   = data
      error.value   = null
    } catch {
      error.value = 'Erro ao carregar estatísticas'
    } finally {
      loading.value = false
    }
  }

  usePolling(fetchStats, 5000)

  return { stats, loading, error }
}
```

**Estados expostos:**
- `stats` — dados ou `null` antes do primeiro fetch
- `loading` — `true` até a primeira resposta
- `error` — mensagem de erro se a API falhar

**Na view:**
```typescript
const { stats, loading, error } = useStats()
// stats é reativo — o template atualiza automaticamente a cada 5s
```

---

## `useEmails` — `useEmails.ts`

Gerencia a listagem paginada de e-mails e a ação de requeue.

```typescript
export function useEmails() {
  const emails  = ref<PaginatedEmails | null>(null)
  const loading = ref(false)
  const error   = ref<string | null>(null)

  async function fetchEmails(params: Record<string, string | number> = {}) { ... }
  async function requeue(id: string): Promise<void> { ... }

  return { emails, loading, error, fetchEmails, requeue }
}
```

**Diferença de `useStats`:** não usa `usePolling` — a atualização é manual (acionada por mudança de página ou filtro). Isso evita que a tabela "pule" enquanto o usuário está interagindo.

**Uso na view:**
```typescript
const { emails, loading, fetchEmails, requeue } = useEmails()

// Carregar página 2 filtrada por 'failed'
fetchEmails({ page: 2, per_page: 20, status: 'failed' })

// Reprocessar e recarregar
await requeue(emailId)
await fetchEmails({ page: currentPage.value })
```

---

## Padrão de Design

Todos os composables seguem o mesmo contrato:
1. Expõem `ref`s reativas para o template
2. Encapsulam tratamento de erro (nunca propagam exceção para o template)
3. Gerenciam `loading` para feedback visual
4. As views **não** importam `api.ts` diretamente — usam composables
