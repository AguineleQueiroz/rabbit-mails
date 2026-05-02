<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

interface ChartPoint {
  hour: string
  sent: number
  failed: number
}

const props = defineProps<{ data: ChartPoint[] }>()

const chartData = computed(() => ({
  labels: props.data.map((d) => {
    const h = new Date(d.hour).getHours().toString().padStart(2, '0')
    return `${h}:00`
  }),
  datasets: [
    {
      label: 'Enviados',
      data: props.data.map((d) => d.sent),
      backgroundColor: 'rgba(249,115,22,0.85)',
      borderColor: '#f97316',
      borderWidth: 0,
    },
    {
      label: 'Falharam',
      data: props.data.map((d) => d.failed),
      backgroundColor: 'rgba(244,63,94,0.7)',
      borderColor: '#f43f5e',
      borderWidth: 0,
    },
  ],
}))

const options = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: {
        color: '#a1a1aa',
        boxWidth: 12,
        padding: 16,
        font: { size: 12 },
      },
    },
    title: { display: false },
    tooltip: {
      backgroundColor: '#18181b',
      titleColor: '#f4f4f5',
      bodyColor: '#a1a1aa',
      borderColor: '#3f3f46',
      borderWidth: 1,
    },
  },
  scales: {
    x: {
      grid: { color: 'rgba(63,63,70,0.4)', drawBorder: false },
      ticks: { color: '#71717a', font: { size: 11 } },
    },
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(63,63,70,0.4)', drawBorder: false },
      ticks: { color: '#71717a', font: { size: 11 }, stepSize: 1 },
    },
  },
}
</script>

<template>
  <div class="bg-zinc-900 border border-zinc-800 p-5">
    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-4">
      Envios por hora — últimas 24h
    </p>
    <Bar :data="chartData" :options="options" />
  </div>
</template>
