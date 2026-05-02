import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockGetStats = vi.fn()

vi.mock('@/services/api', () => ({
  dashboardApi: { getStats: mockGetStats },
}))

vi.mock('@/composables/usePolling', () => ({
  usePolling: (callback: () => void) => callback(),
}))

describe('useStats', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('preenche stats após chamada bem-sucedida', async () => {
    const mockData = {
      queue:     { pending: 5, processing: 1 },
      emails:    { sent_today: 10, failed_today: 1, dead: 0, total: 100 },
      consumers: { active: 2, idle: 0 },
    }
    mockGetStats.mockResolvedValueOnce({ data: mockData })

    const { useStats } = await import('@/composables/useStats')
    const { stats, loading } = useStats()

    await vi.waitUntil(() => !loading.value)

    expect(stats.value).toEqual(mockData)
    expect(loading.value).toBe(false)
  })

  it('define erro quando API falha', async () => {
    mockGetStats.mockRejectedValueOnce(new Error('Network Error'))

    const { useStats } = await import('@/composables/useStats')
    const { error, loading } = useStats()

    await vi.waitUntil(() => !loading.value)

    expect(error.value).toBeTruthy()
  })
})
