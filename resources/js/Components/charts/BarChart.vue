<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
  ChartOptions,
} from 'chart.js'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps<{
  labels: string[]
  values: number[]
  title?: string
  barColor?: string
}>()

const data = computed(() => ({
  labels: props.labels,
  datasets: [{
    data: props.values,
    backgroundColor: props.barColor ?? 'rgba(99, 102, 241, 0.8)',
    borderColor: props.barColor ?? 'rgba(99, 102, 241, 1)',
    borderWidth: 1,
    borderRadius: 6,
  }],
}))

const options = computed<ChartOptions<'bar'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: {
        color: 'rgba(255,255,255,0.6)',
        font: { size: 11 },
      },
    },
    y: {
      grid: { color: 'rgba(255,255,255,0.06)' },
      ticks: {
        color: 'rgba(255,255,255,0.6)',
        font: { size: 11 },
      },
      beginAtZero: true,
    },
  },
  animation: { duration: 600 },
}))
</script>

<template>
  <div class="h-64 w-full">
    <Bar :data="data" :options="options" />
  </div>
</template>
