<script setup lang="ts">
import { ref } from 'vue'
import { dashboardApi } from '@/services/api'
import { useStats } from '@/composables/useStats'
import { usePolling } from '@/composables/usePolling'
import StatsCards from '@/components/StatsCards.vue'
import EmailChart from '@/components/charts/EmailChart.vue'
import ConsumerPanel from '@/components/ConsumerPanel.vue'
import EventLog from '@/components/EventLog.vue'
import type { Consumer, QueueEvent } from '@/types'

const { stats, loading: statsLoading } = useStats()

const chartData = ref<{ hour: string; sent: number; failed: number }[]>([])
const consumers = ref<Consumer[]>([])
const events    = ref<QueueEvent[]>([])

async function fetchChart()     { const { data } = await dashboardApi.getChart();     chartData.value = data }
async function fetchConsumers() { const { data } = await dashboardApi.getConsumers(); consumers.value = data }
async function fetchEvents()    { const { data } = await dashboardApi.getEvents(20);  events.value    = data }

usePolling(fetchChart,     10000)
usePolling(fetchConsumers, 5000)
usePolling(fetchEvents,    5000)
</script>

<template>
  <div class="space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-zinc-100">Dashboard</h1>
        <p class="text-xs text-zinc-600 mt-0.5">Monitoramento em tempo real · atualiza a cada 5s</p>
      </div>
    </div>

    <div v-if="statsLoading" class="h-24 flex items-center justify-center text-zinc-600 text-sm">
      Carregando métricas...
    </div>
    <StatsCards v-else-if="stats" :stats="stats" />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <EmailChart v-if="chartData.length" :data="chartData" />
        <div v-else class="bg-zinc-900 border border-zinc-800 p-5 flex items-center justify-center h-48 text-zinc-700 text-sm">
          Aguardando dados do gráfico...
        </div>
      </div>
      <ConsumerPanel :consumers="consumers" />
    </div>

    <EventLog :events="events" />

  </div>
</template>
