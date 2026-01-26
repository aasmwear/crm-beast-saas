<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'

import Card from '@/Components/ui/Card.vue'
import KpiTile from '@/Components/ui/KpiTile.vue'
import DonutChart from '@/Components/charts/DonutChart.vue'
import MiniArea from '@/Components/charts/MiniArea.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Expose the tenant slug for template usage (SSR-safe via globalThis)
const tenantSlug = computed<string>(() => (globalThis as any).tenant?.slug ?? 'acme')
const props = defineProps<{
  tenant?: { id:number; name:string; slug:string } | null
  kpis?: Record<string, number>
  taskTrend7?: number[]
  workload?: { labels:string[]; values:number[] }
  activity?: Array<{ id:number; title:string; status:string }>
}>()

const orgName = computed(()=> props.tenant?.name ?? 'Your Workspace')
const trend = computed(()=> props.taskTrend7 ?? [12,14,10,18,22,21,28])
const workload = computed(()=> props.workload ?? ({ labels: ['SEO','SMM','PPC','Dev'], values: [35,25,20,20] }))
const kpis = computed(()=> ({
  mrr: props.kpis?.mrr ?? 170329.4,
  perf: props.kpis?.perf ?? 2.34,
  efficiency: props.kpis?.efficiency ?? 64,
  resilience: props.kpis?.resilience ?? 0.64,
  score: props.kpis?.score ?? 71,
}))
</script>

<template>


  <header class="relative hero-bg rounded-3xl p-6 mb-6 overflow-hidden">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p class="text-sm text-white/60 mt-1">Overview and quick actions</p>
      </div>
      <div class="flex gap-2">
        <button class="chip">Filter</button>
        <a :href="route('clients.index', { organization: tenantSlug })" class="chip">Export</a>
      </div>
    </div>
  </header>

<Head title="Dashboard" />

  <AuthenticatedLayout>
    <template #default>
      <!-- Hero -->
      <div class="gradient-hero rounded-3xl p-6 md:p-8">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-sm text-foreground/80">Dashboard / Overview</div>
            <h1 class="text-3xl md:text-4xl font-bold mt-1">Hello {{ orgName }}! 👋</h1>
            <p class="text-sm text-foreground/80 mt-1">Let’s check how things are performing today.</p>
          </div>
          <div class="flex items-center gap-2">
            <button class="btn-capsule">Filter</button>
            <button class="btn-capsule">Export</button>
          </div>
        </div>

        <div class="glass grid grid-cols-2 md:grid-cols-4 gap-3 mt-6">
          <KpiTile label="Monthly Performance" :value="kpis.perf + '%'" trend="+0.6%" positive />
          <KpiTile label="Asset Efficiency" :value="kpis.efficiency + '%'" trend="+2%" positive />
          <KpiTile label="Market Resilience" :value="kpis.resilience" />
          <KpiTile label="Financial Strength" :value="kpis.score + '/100'" />
        </div>
      </div>

      <!-- Charts row -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mt-6">
        <div class="md:col-span-3">
          <MiniArea :labels="['M','T','W','T','F','S','S']" :values="trend" title="7d Trend" />
        </div>
        <div class="md:col-span-2">
          <DonutChart :labels="workload.labels" :values="workload.values" center="$170+" />
        </div>
      </div>

      <!-- Transactions / Activity -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <Card title="Transactions">
          <div class="divide-y divide-white/5">
            <div class="flex items-center justify-between py-3" v-for="i in 5" :key="i">
              <div class="text-sm text-foreground/80">Item {{ i }}</div>
              <div class="text-xs text-success">Success</div>
            </div>
          </div>
        </Card>

        <Card title="Trending">
          <MiniArea :labels="['M','T','W','T','F','S','S']" :values="trend" />
        </Card>
      </div>
    </template>
  </AuthenticatedLayout>
</template>
