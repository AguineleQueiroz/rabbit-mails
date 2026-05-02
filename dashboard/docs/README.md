# Dashboard — Email Queue

SPA Vue 3 com TypeScript que monitora em tempo real o sistema de filas: e-mails, workers, gráficos e log de eventos.

## Stack

| Tecnologia | Versão | Função |
|---|---|---|
| Vue 3 | 3.4 | Framework reativo (Composition API) |
| TypeScript | 5.x | Tipagem estática |
| Vite 5 | 5.x | Build tool e dev server |
| Tailwind CSS | 3.x | Estilização utilitária |
| Axios | 1.x | Cliente HTTP |
| Chart.js + vue-chartjs | 4.x | Gráficos |
| Vue Router | 4.x | Roteamento SPA |
| Vitest | 1.x | Testes unitários e de componentes |

## Arquitetura

```
src/
├── types/         ← Interfaces TypeScript compartilhadas
├── services/      ← Cliente HTTP (Axios) — única camada que chama a API
├── composables/   ← Lógica reutilizável (polling, fetch de dados)
├── router/        ← Definição de rotas da SPA
├── components/    ← Componentes visuais puros (recebem dados via props)
└── views/         ← Páginas: combinam composables + componentes
```

## Desenvolvimento

```bash
# Subir dashboard com hot-reload
docker compose up -d dashboard

# Acessar
open http://localhost:5173

# Instalar dependências manualmente
docker compose run --rm dashboard npm install

# Rodar testes
docker compose exec dashboard npm run test
```

## Build para Produção

```bash
docker compose exec dashboard npm run build
# Saída em dashboard/dist/
# Servir com Nginx usando dashboard/nginx/nginx.conf
```

## Páginas

| Rota | View | Descrição |
|---|---|---|
| `/` | DashboardView | Cards + gráfico + workers + eventos recentes |
| `/emails` | EmailsView | Tabela paginada com filtros e reprocessamento |
| `/consumers` | ConsumersView | Status dos workers em tempo real |
| `/events` | EventsView | Feed completo de eventos da fila |

## Documentação por Área

- [Tipos TypeScript](types.md)
- [Serviço HTTP](services.md)
- [Composables](composables.md)
- [Componentes](components.md)
- [Views e Roteamento](views.md)
- [Nginx (Produção)](nginx.md)
- [Testes](testing.md)
