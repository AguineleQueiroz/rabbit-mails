<script setup lang="ts">
import type { Consumer } from '@/types'

defineProps<{ consumers: Consumer[] }>()

const statusDot: Record<Consumer['status'], string> = {
  active:  'bg-emerald-500',
  idle:    'bg-amber-400',
  stopped: 'bg-rose-500',
}

const statusText: Record<Consumer['status'], string> = {
  active:  'text-emerald-400',
  idle:    'text-amber-400',
  stopped: 'text-rose-400',
}

function relativeTime(iso: string): string {
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60)   return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}m`
  return `${Math.floor(diff / 3600)}h`
}
</script>

<template>
  <div class="bg-zinc-900 border border-zinc-800">
    <div class="px-5 py-3 border-b border-zinc-800 flex items-center justify-between">
      <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Workers</p>
      <span class="text-xs text-zinc-600">{{ consumers.length }} registrados</span>
    </div>

    <div v-if="!consumers.length" class="px-5 py-10 text-center text-zinc-600 text-sm">
      Nenhum worker ativo
    </div>

    <ul class="divide-y divide-zinc-800">
      <li v-for="c in consumers" :key="c.worker_id" class="px-5 py-3 flex items-center gap-3">
        <span :class="statusDot[c.status]" class="w-2 h-2 shrink-0 block" />
        <div class="flex-1 min-w-0">
          <p class="text-sm text-zinc-200 truncate font-mono">{{ c.worker_id }}</p>
          <p class="text-xs text-zinc-600 mt-0.5">
            heartbeat {{ relativeTime(c.last_heartbeat) }} atrás ·
            {{ c.emails_processed }} enviados ·
            {{ c.emails_failed }} falhas
          </p>
        </div>
        <span :class="statusText[c.status]" class="text-xs font-semibold uppercase tracking-wider">
          {{ c.status }}
        </span>
      </li>
    </ul>
  </div>
</template>
