<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, ChartOptions
} from 'chart.js'

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend)

const props = defineProps<{ labels: string[]; values: number[]; title?: string }>()

const data = computed(()=> ({
  labels: props.labels,
  datasets: [{
    data: props.values,
    fill: true,
    tension: 0.35,
    pointRadius: 0,
    borderWidth: 2,
    borderColor: 'rgba(168,85,247, .9)',
    backgroundColor: (ctx: any) => {
      const {ctx: c} = ctx.chart
      const g = c.createLinearGradient(0,0,0,200)
      g.addColorStop(0, 'rgba(168,85,247,.35)')
      g.addColorStop(1, 'rgba(168,85,247,.02)')
      return g
    }
  }]
}))

const options = computed<ChartOptions<'line'>>(()=> ({
  responsive: true,
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
  interaction: { intersect: false, mode: 'index' },
  scales: {
    x: { grid: { display: false }, ticks: { display: false } },
    y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { display: false } },
  },
  animation: { duration: 900 },
}))
</script>

<template>
  <div class="glass-card rounded-3xl p-4 h-64">
    <div v-if="title" class="text-sm text-muted-foreground mb-2">{{ title }}</div>
    <Line :data="data" :options="options" />
  </div>
</template>
