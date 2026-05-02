<script setup lang="ts">
import type { QueueEvent } from '@/types'

defineProps<{ events: QueueEvent[] }>()

const eventStyle: Record<string, string> = {
  published:    'text-sky-400    bg-sky-400/10',
  processing:   'text-amber-400  bg-amber-400/10',
  sent:         'text-emerald-400 bg-emerald-400/10',
  failed:       'text-rose-400   bg-rose-400/10',
  retried:      'text-orange-400 bg-orange-400/10',
  dead_lettered:'text-rose-600   bg-rose-600/10',
  requeued:     'text-violet-400 bg-violet-400/10',
}
</script>

<template>
  <div class="bg-zinc-900 border border-zinc-800">
    <div class="px-5 py-3 border-b border-zinc-800">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Log de Eventos</p>
    </div>

    <div v-if="!events.length" class="px-5 py-10 text-center text-zinc-600 text-sm">
      Sem eventos registrados
    </div>

    <ul class="divide-y divide-zinc-800/60 max-h-80 overflow-y-auto">
      <li v-for="ev in events" :key="ev.id" class="px-5 py-2.5 flex items-start gap-3 text-sm hover:bg-zinc-800/40 transition-colors">
        <span
          :class="eventStyle[ev.event] ?? 'text-zinc-400 bg-zinc-400/10'"
          class="shrink-0 mt-0.5 px-1.5 py-0.5 text-xs font-mono font-semibold uppercase tracking-wide"
        >
          {{ ev.event.replace('_', ' ') }}
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-zinc-300 truncate">
            {{ ev.recipient }}
            <span class="text-zinc-600 mx-1">—</span>
            <span class="text-zinc-500">{{ ev.subject }}</span>
          </p>
          <p v-if="ev.worker_id" class="text-xs text-zinc-600 font-mono mt-0.5">{{ ev.worker_id }}</p>
        </div>
        <span class="text-xs text-zinc-600 shrink-0 tabular-nums">
          {{ new Date(ev.created_at).toLocaleTimeString('pt-BR') }}
        </span>
      </li>
    </ul>
  </div>
</template>
