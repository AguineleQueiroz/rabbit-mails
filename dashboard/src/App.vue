<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-zinc-950">
    <nav class="bg-zinc-900 border-b border-zinc-800">
      <div class="max-w-7xl mx-auto px-6">
        <div class="flex items-center justify-between h-14">
          <RouterLink to="/" class="flex items-center gap-2">
            <span class="text-orange-500 font-bold tracking-widest text-sm uppercase">Rabbit</span>
            <span class="text-zinc-100 font-bold tracking-widest text-sm uppercase">Mail</span>
          </RouterLink>
          <div class="flex gap-1 text-sm">
            <RouterLink
              v-for="link in [
                { to: '/',          label: 'Dashboard' },
                { to: '/emails',    label: 'E-mails' },
                { to: '/consumers', label: 'Workers' },
                { to: '/events',    label: 'Eventos' },
              ]"
              :key="link.to"
              :to="link.to"
              class="px-3 py-1.5 text-zinc-400 hover:text-zinc-100 transition-colors"
              active-class="text-orange-500 bg-orange-500/10"
              exact-active-class="text-orange-500 bg-orange-500/10"
            >
              {{ link.label }}
            </RouterLink>
          </div>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto px-6 py-8">
      <RouterView />
    </main>
  </div>
</template>
