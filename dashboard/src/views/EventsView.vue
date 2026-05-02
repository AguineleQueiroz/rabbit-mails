<script setup lang="ts">
import { ref } from 'vue'
import { dashboardApi } from '@/services/api'
import { usePolling } from '@/composables/usePolling'
import EventLog from '@/components/EventLog.vue'
import type { QueueEvent } from '@/types'

const events = ref<QueueEvent[]>([])

async function fetch() {
  const { data } = await dashboardApi.getEvents(100)
  events.value = data
}

usePolling(fetch, 5000)
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-lg font-semibold text-zinc-100">Eventos</h1>
      <p class="text-xs text-zinc-600 mt-0.5">Log de auditoria da fila em tempo real</p>
    </div>
    <EventLog :events="events" />
  </div>
</template>
