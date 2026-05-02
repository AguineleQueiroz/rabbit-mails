<script setup lang="ts">
import type { Stats } from '@/types'

defineProps<{ stats: Stats }>()
</script>

<template>
  <div class="grid grid-cols-2 gap-4 md:grid-cols-4">

    <div data-testid="stat-card" class="bg-zinc-900 border border-zinc-800 border-l-2 border-l-orange-500 p-5">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Na Fila</p>
      <p class="mt-2 text-4xl font-bold text-zinc-100">{{ stats.queue.pending }}</p>
      <p class="mt-1 text-xs text-zinc-500">
        <span class="text-orange-400">{{ stats.queue.processing }}</span> processando
      </p>
    </div>

    <div data-testid="stat-card" class="bg-zinc-900 border border-zinc-800 border-l-2 border-l-emerald-500 p-5">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Enviados hoje</p>
      <p class="mt-2 text-4xl font-bold text-zinc-100">{{ stats.emails.sent_today }}</p>
      <p class="mt-1 text-xs text-zinc-500">
        <span class="text-zinc-400">{{ stats.emails.total }}</span> total
      </p>
    </div>

    <div data-testid="stat-card" class="bg-zinc-900 border border-zinc-800 border-l-2 border-l-rose-500 p-5">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Falharam hoje</p>
      <p class="mt-2 text-4xl font-bold text-zinc-100">{{ stats.emails.failed_today }}</p>
      <p class="mt-1 text-xs text-zinc-500">
        <span class="text-rose-400">{{ stats.emails.dead }}</span> dead letter
      </p>
    </div>

    <div data-testid="stat-card" class="bg-zinc-900 border border-zinc-800 border-l-2 border-l-sky-500 p-5">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Workers ativos</p>
      <p class="mt-2 text-4xl font-bold text-zinc-100">{{ stats.consumers.active }}</p>
      <p class="mt-1 text-xs text-zinc-500">
        <span class="text-sky-400">{{ stats.consumers.idle }}</span> idle
      </p>
    </div>

  </div>
</template>
