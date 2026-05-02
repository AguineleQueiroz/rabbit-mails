<script setup lang="ts">
import { ref } from 'vue'
import { useEmails } from '@/composables/useEmails'
import EmailTable from '@/components/EmailTable.vue'

const { emails, loading, fetchEmails, requeue } = useEmails()

const currentPage   = ref(1)
const currentStatus = ref('')

async function load() {
  await fetchEmails({ page: currentPage.value, per_page: 20, status: currentStatus.value })
}

async function handleRequeue(id: string) {
  await requeue(id)
  await load()
}

function handlePageChange(page: number) {
  currentPage.value = page
  load()
}

function handleFilterChange(status: string) {
  currentStatus.value = status
  currentPage.value   = 1
  load()
}

load()
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-lg font-semibold text-zinc-100">E-mails</h1>
      <p class="text-xs text-zinc-600 mt-0.5">Histórico completo de mensagens na fila</p>
    </div>
    <EmailTable
      v-if="emails"
      :emails="emails.data"
      :total="emails.total"
      :page="emails.page"
      :total-pages="emails.total_pages"
      :loading="loading"
      @page-change="handlePageChange"
      @filter-change="handleFilterChange"
      @requeue="handleRequeue"
    />
  </div>
</template>
