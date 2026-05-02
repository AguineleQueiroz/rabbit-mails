import { ref } from 'vue'
import { emailsApi } from '@/services/api'
import type { PaginatedEmails } from '@/types'

export function useEmails() {
  const emails  = ref<PaginatedEmails | null>(null)
  const loading = ref(false)
  const error   = ref<string | null>(null)

  async function fetchEmails(params: Record<string, string | number> = {}) {
    loading.value = true
    try {
      const { data } = await emailsApi.list(params)
      emails.value  = data
      error.value   = null
    } catch {
      error.value = 'Erro ao carregar e-mails'
    } finally {
      loading.value = false
    }
  }

  async function requeue(id: string): Promise<void> {
    await emailsApi.requeue(id)
  }

  return { emails, loading, error, fetchEmails, requeue }
}
