<script setup lang="ts">
import { ref } from 'vue'
import { dashboardApi } from '@/services/api'
import { usePolling } from '@/composables/usePolling'
import ConsumerPanel from '@/components/ConsumerPanel.vue'
import type { Consumer } from '@/types'

const consumers = ref<Consumer[]>([])

async function fetch() {
  const { data } = await dashboardApi.getConsumers()
  consumers.value = data
}

usePolling(fetch, 5000)
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-lg font-semibold text-zinc-100">Workers</h1>
      <p class="text-xs text-zinc-600 mt-0.5">Status dos consumers RabbitMQ via heartbeat Redis</p>
    </div>
    <ConsumerPanel :consumers="consumers" />
  </div>
</template>
