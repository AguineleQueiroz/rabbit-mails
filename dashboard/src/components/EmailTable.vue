<script setup lang="ts">
import type { Email } from '@/types'

const props = defineProps<{
  emails: Email[]
  total: number
  page: number
  totalPages: number
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
  (e: 'filter-change', status: string): void
  (e: 'requeue', id: string): void
}>()

const statusStyle: Record<string, string> = {
  pending:    'text-zinc-400   bg-zinc-400/10',
  queued:     'text-sky-400    bg-sky-400/10',
  processing: 'text-amber-400  bg-amber-400/10',
  sent:       'text-emerald-400 bg-emerald-400/10',
  failed:     'text-rose-400   bg-rose-400/10',
  dead:       'text-rose-600   bg-rose-600/10',
}
</script>

<template>
  <div class="bg-zinc-900 border border-zinc-800 overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3 border-b border-zinc-800">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">E-mails</p>
      <select
        class="text-xs bg-zinc-800 border border-zinc-700 text-zinc-300 px-2 py-1.5 focus:outline-none focus:border-orange-500 cursor-pointer"
        @change="emit('filter-change', ($event.target as HTMLSelectElement).value)"
      >
        <option value="">Todos os status</option>
        <option value="queued">Na fila</option>
        <option value="processing">Processando</option>
        <option value="sent">Enviados</option>
        <option value="failed">Falharam</option>
        <option value="dead">Dead letter</option>
      </select>
    </div>

    <div v-if="loading" class="px-5 py-10 text-center text-zinc-600 text-sm">
      Carregando...
    </div>

    <table v-else class="w-full text-sm">
      <thead class="border-b border-zinc-800">
        <tr>
          <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600">Destinatário</th>
          <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600">Assunto</th>
          <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600">Status</th>
          <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600">Tentativas</th>
          <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600">Criado</th>
          <th class="px-5 py-2.5"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-800/60">
        <tr
          v-for="email in emails"
          :key="email.id"
          class="hover:bg-zinc-800/40 transition-colors"
        >
          <td class="px-5 py-3 text-zinc-300 font-mono text-xs">{{ email.recipient }}</td>
          <td class="px-5 py-3 text-zinc-300 max-w-xs truncate">{{ email.subject }}</td>
          <td class="px-5 py-3">
            <span
              :class="statusStyle[email.status]"
              class="px-1.5 py-0.5 text-xs font-mono font-semibold uppercase tracking-wide"
            >{{ email.status }}</span>
          </td>
          <td class="px-5 py-3 text-zinc-500 tabular-nums">{{ email.attempts }}</td>
          <td class="px-5 py-3 text-zinc-600 text-xs tabular-nums">
            {{ new Date(email.created_at).toLocaleString('pt-BR') }}
          </td>
          <td class="px-5 py-3">
            <button
              v-if="['failed', 'dead'].includes(email.status)"
              class="text-xs font-semibold text-orange-500 hover:text-orange-400 uppercase tracking-wider transition-colors"
              @click="emit('requeue', email.id)"
            >
              Reprocessar
            </button>
          </td>
        </tr>
        <tr v-if="!emails.length">
          <td colspan="6" class="px-5 py-10 text-center text-zinc-600">Nenhum e-mail encontrado</td>
        </tr>
      </tbody>
    </table>

    <div class="flex items-center justify-between px-5 py-3 border-t border-zinc-800 text-xs text-zinc-600">
      <span>{{ total }} registro(s)</span>
      <div class="flex items-center gap-1">
        <button
          :disabled="page <= 1"
          class="px-3 py-1.5 border border-zinc-700 text-zinc-400 hover:border-orange-500 hover:text-orange-500 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          @click="emit('page-change', page - 1)"
        >← Anterior</button>
        <span class="px-3 py-1.5 text-zinc-500">{{ page }} / {{ totalPages }}</span>
        <button
          :disabled="page >= totalPages"
          class="px-3 py-1.5 border border-zinc-700 text-zinc-400 hover:border-orange-500 hover:text-orange-500 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          @click="emit('page-change', page + 1)"
        >Próxima →</button>
      </div>
    </div>
  </div>
</template>
