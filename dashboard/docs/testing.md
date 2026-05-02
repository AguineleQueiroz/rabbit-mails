# Testes — Frontend Vue.js

Framework: [Vitest 1.x](https://vitest.dev) — compatível com Vite, API idêntica ao Jest.
Utilitários: `@vue/test-utils` para montar componentes e interagir com o DOM.

## Estrutura

```
tests/
├── vitest.setup.ts       ← Configura router global para todos os testes
├── StatsCards.spec.ts    ← Testa renderização dos cards de métricas
├── EmailTable.spec.ts    ← Testa tabela: filtros, paginação, requeue, loading
└── useStats.spec.ts      ← Testa composable: polling, fetch, estado de erro
```

## Executar

```bash
# Rodar uma vez (modo CI)
docker compose exec dashboard npm run test

# Modo watch (reexecuta ao salvar)
docker compose exec dashboard npm run test:watch

# Diretamente (sem Docker, se node instalado localmente)
cd dashboard && npm run test
```

## `vitest.setup.ts`

Configura um router in-memory para todos os testes — necessário porque `App.vue` usa `<RouterLink>` e `<RouterView>`:

```typescript
import { config } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', component: { template: '<div/>' } }],
})

config.global.plugins = [router]
```

`createMemoryHistory()` não usa o URL do browser — adequado para testes unitários.

## `StatsCards.spec.ts`

Testa que os valores das props aparecem corretamente no DOM:

```typescript
const mockStats: Stats = {
  queue:     { pending: 12, processing: 3 },
  emails:    { sent_today: 342, ... },
  consumers: { active: 2, idle: 0 },
}

it('exibe quantidade na fila corretamente', () => {
  const wrapper = mount(StatsCards, { props: { stats: mockStats } })
  expect(wrapper.text()).toContain('12')
})
```

`mount()` renderiza o componente em memória (JSDOM). `wrapper.text()` retorna todo o texto visível.

## `EmailTable.spec.ts`

Testa comportamentos interativos — cliques, selects e emissão de eventos:

```typescript
it('emite requeue com id correto ao clicar em Reprocessar', async () => {
  const email   = makeEmail({ status: 'failed' })
  const wrapper = mount(EmailTable, { props: { emails: [email], ... } })

  await wrapper.find('button').trigger('click')
  expect(wrapper.emitted('requeue')?.[0]).toEqual(['uuid-1'])
})

it('emite filter-change ao mudar o select', async () => {
  const wrapper = mount(EmailTable, { props: defaultProps })
  await wrapper.find('select').setValue('failed')
  expect(wrapper.emitted('filter-change')?.[0]).toEqual(['failed'])
})
```

**`wrapper.emitted()`** captura todos os eventos emitidos pelo componente — útil para testar `defineEmits` sem precisar de uma view pai.

## `useStats.spec.ts`

Testa o composable em isolamento usando `vi.mock()` para substituir `api.ts`:

```typescript
vi.mock('@/services/api', () => ({
  dashboardApi: { getStats: mockGetStats },
}))

// Mock de usePolling para executar o callback imediatamente (sem timer real)
vi.mock('@/composables/usePolling', () => ({
  usePolling: (callback: () => void) => callback(),
}))

it('preenche stats após chamada bem-sucedida', async () => {
  mockGetStats.mockResolvedValueOnce({ data: mockData })
  const { stats, loading } = useStats()
  await vi.waitUntil(() => !loading.value)
  expect(stats.value).toEqual(mockData)
})
```

**Por que mockar `usePolling`?** O composable real usa `onMounted` + `setInterval`. Em testes, não há ciclo de vida de componente ativo — mockar garante que o callback é chamado imediatamente e de forma síncrona.

## Ambiente JSDOM

`vite.config.ts`:
```typescript
test: {
  environment: 'jsdom',  // Simula o DOM do browser
  globals: true,         // Disponibiliza describe/it/expect globalmente
  setupFiles: ['./tests/vitest.setup.ts'],
}
```

JSDOM é um parser de HTML + implementação parcial do DOM em Node.js — permite renderizar componentes Vue sem browser real.
