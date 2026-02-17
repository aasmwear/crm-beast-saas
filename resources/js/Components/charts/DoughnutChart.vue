<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  ChartOptions,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{
  labels: string[]
  values: number[]
  colors?: string[]
}>()

const defaultColors = ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4']

const data = computed(() => ({
  labels: props.labels,
  datasets: [{
    data: props.values,
    backgroundColor: props.colors ?? defaultColors,
    borderWidth: 0,
    hoverOffset: 8,
  }],
}))

const options = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '60%',
  plugins: {
    legend: {
      display: true,
      position: 'bottom',
      labels: {
        color: 'rgba(255,255,255,0.8)',
        font: { size: 11 },
        padding: 12,
      },
    },
    tooltip: { enabled: true },
  },
  animation: { duration: 600 },
}))
</script>

<template>
  <div class="h-64 w-full">
    <Doughnut :data="data" :options="options" />
  </div>
</template>
