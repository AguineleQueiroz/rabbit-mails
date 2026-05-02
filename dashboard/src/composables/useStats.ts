import { ref } from 'vue'
import { dashboardApi } from '@/services/api'
import { usePolling } from './usePolling'
import type { Stats } from '@/types'

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
