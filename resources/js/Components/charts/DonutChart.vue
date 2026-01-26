<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS, ArcElement, Tooltip, Legend, Plugin, ChartOptions
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{
  labels: string[]
  values: number[]
  colors?: string[]
  center?: string
}>()

// center text
const centerText: Plugin<'doughnut'> = {
  id: 'centerText',
  beforeDraw(chart) {
    const { width, height, ctx } = chart
    ctx.save()
    ctx.font = '700 18px ui-sans-serif, system-ui, -apple-system'
    ctx.fillStyle = '#eaeef6'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText((chart.options as any).centerLabel || '', width / 2, height / 2)
    ctx.restore()
  }
}

const data = computed(()=> ({
  labels: props.labels,
  datasets: [{
    data: props.values,
    backgroundColor: props.colors ?? ['#7c3aed','#22c55e','#06b6d4','#f59e0b','#ef4444'],
    borderWidth: 0,
    hoverOffset: 8,
  }],
}))

const options = computed<ChartOptions<'doughnut'>>(()=> ({
  responsive: true,
  cutout: '68%',
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
  animation: { duration: 900 },
  centerLabel: props.center ?? '',
} as any))

onMounted(()=> { ChartJS.register(centerText) })
</script>

<template>
  <div class="glass-card rounded-3xl p-4 h-72">
    <Doughnut :data="data" :options="options" />
  </div>
</template>
