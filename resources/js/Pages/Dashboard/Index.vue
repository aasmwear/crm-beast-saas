<template>
  <div class="space-y-6">
    <!-- Hero -->
    <section
      class=".hero-slab"
    >
      <div class="flex items-end justify-between gap-6">
        <div>
          <div class="text-sm text-white/60">Organization • {{ orgName }}</div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">Hello there <span>👋</span></h1>
          <p class="mt-1 text-white/60">Let’s check how things are performing today.</p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Date Range Button (newly added) -->
          <DateRangeButton 
            :start-date-raw="rawStartDate"
            :end-date-raw="rawEndDate"
            :start-date="dateRange.start" 
            :end-date="dateRange.end" 
          />
        </div>
      </div>
    </section>

    <!-- KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card title="Clients (30d) card-neo">
        <div class="text-4xl font-semibold mb-2">{{ stats.clients }}</div>
        <MiniArea :labels="clients30d.labels" :values="clients30d.values" />
      </Card>
      <Card title="Projects (30d) card-neo">
        <div class="text-4xl font-semibold mb-2">{{ stats.projects }}</div>
        <MiniArea :labels="projects30d.labels" :values="projects30d.values" />
      </Card>
      <Card title="Tasks (30d) card-neo">
        <div class="text-4xl font-semibold mb-2">{{ stats.tasks }}</div>
        <MiniArea :labels="tasks30d.labels" :values="tasks30d.values" />
      </Card>
    </section>

    <!-- Charts + lists -->
     <section class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <Card class="lg:col-span-3" title="Open tasks (by assignee)">
            <MiniArea :labels="tasksByAssignee.labels" :values="tasksByAssignee.values" />
        </Card>

        <Card class="lg:col-span-1" title="Workload by status">
            <DonutChart :labels="workload.labels" :values="workload.values" />
            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <div v-for="(label, i) in workload.labels" :key="i" class="flex items-center gap-2 text-white/70">
                    <span class="inline-block h-2.5 w-2.5 rounded-full border border-white/30"
                          :style="{ backgroundColor: donutColors[i % donutColors.length] }" />
                    <span class="truncate">{{ label }}</span>
                    <span class="ml-auto text-white/60">{{ workload.values[i] }}</span>
                </div>
            </div>
        </Card>
    </section>
    <!-- <section class="grid grid-cols-1 lg:col-span-3 gap-6">
      <Card class="lg:col-span-1" title="Workload by status">
        <DonutChart :labels="workload.labels" :values="workload.values" />
        <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
          <div v-for="(label, i) in workload.labels" :key="i" class="flex items-center gap-2 text-white/70">
            <span class="inline-block h-2.5 w-2.5 rounded-full border border-white/30"
                  :style="{ backgroundColor: donutColors[i % donutColors.length] }" />
            <span class="truncate">{{ label }}</span>
            <span class="ml-auto text-white/60">{{ workload.values[i] }}</span>
          </div>
        </div>
      </Card>

      <Card class="lg:col-span-2" title="Recent activity">
        <template v-if="recent.length">
          <ul class="divide-y divide-white/10">
            <li v-for="item in recent" :key="item.id" class="py-3 flex items-center gap-3">
              <span class="text-sm text-white/60 w-28">{{ item.when }}</span>
              <span class="text-sm">{{ item.message }}</span>
            </li>
          </ul>
        </template>
        <div v-else class="text-white/50 text-sm">Nothing yet — create a client, project, or task to see activity here.</div>
      </Card>
    </section> -->

    <section class="grid grid-cols-1 lg:col-span-3 gap-6">
      <Card class="lg:col-span-1" title="Upcoming deadlines">
        <ul v-if="deadlines.length" class="space-y-2">
          <li v-for="d in deadlines" :key="d.id" class="flex items-center justify-between text-sm">
            <span class="truncate">{{ d.title }}</span>
            <span class="text-white/60">{{ d.due }}</span>
          </li>
        </ul>
        <div v-else class="text-white/50 text-sm">No upcoming deadlines.</div>
      </Card>

      <!-- <Card class="lg:col-span-2" title="Open tasks (by assignee)">
        <MiniArea :labels="tasksByAssignee.labels" :values="tasksByAssignee.values" />
      </Card> -->

      <Card class="lg:col-span-2" title="Recent activity">
        <template v-if="recent.length">
          <ul class="divide-y divide-white/10">
            <li v-for="item in recent" :key="item.id" class="py-3 flex items-center gap-3">
              <span class="text-sm text-white/60 w-28">{{ item.when }}</span>
              <span class="text-sm">{{ item.message }}</span>
            </li>
          </ul>
        </template>
        <div v-else class="text-white/50 text-sm">Nothing yet — create a client, project, or task to see activity here.</div>
      </Card>
    </section>
  </div>
</template>

<style scoped>
.chip {
  @apply px-3 py-1.5 rounded-xl bg-[var(--primary,#684EF4)] text-sm font-medium hover:opacity-90 transition whitespace-nowrap;
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3' 
import Card from '@/Components/ui/Card.vue'
import MiniArea from '@/Components/charts/MiniArea.vue'
import DonutChart from '@/Components/charts/DonutChart.vue'
import DateRangeButton from '@/Components/ui/DateRangeButton.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const page = usePage<any>()

/* eslint-disable @typescript-eslint/ban-ts-comment */
// @ts-ignore
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  // @ts-ignore
  (window as any).route(name, params, absolute, config)
// --- end add ---


const orgName = computed(() => (page.props as any)?.org?.name ?? 'ACME Digital')

const org = computed(() => {
  // @ts-ignore ziggy helper
  const p = (route() as any)?.params ?? {}
  if (p.organization) return p.organization
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

// CRITICAL FIX: Use direct prop access and provide a safe fallback (the previous error was here)
const dateRange = computed(() => ({
  start: page.props.startDateFormatted ?? '30 Days Ago',
  end: page.props.endDateFormatted ?? 'Today'
}))


const props = defineProps<{
  stats: { clients: number; projects: number; tasks: number }
  charts?: {
    clients30d?: { labels: string[]; values: number[] }
    projects30d?: { labels: string[]; values: number[] }
    tasks30d?: { labels: string[]; values: number[] }
    workload?: { labels: string[]; values: number[] }
    tasksByAssignee?: { labels: string[]; values: number[] }
  }
  recent?: Array<{ id: number | string; when: string; message: string }>
  deadlines?: Array<{ id: number | string; title: string; due: string }>
  // IMPORTANT: The raw dates are passed down to the DateRangeButton
  startDateFormatted?: string;
  endDateFormatted?: string;
  rawStartDate: string; // Must be present
  rawEndDate: string; // Must be present
}>()

const labels30 = Array.from({ length: 12 }, (_, i) => `W${i + 1}`)
const zeros12 = Array(12).fill(0)

const clients30d   = computed(() => props.charts?.clients30d   ?? { labels: labels30, values: zeros12 })
const projects30d  = computed(() => props.charts?.projects30d  ?? { labels: labels30, values: zeros12 })
const tasks30d     = computed(() => props.charts?.tasks30d     ?? { labels: labels30, values: zeros12 })

const workload = computed(() =>
  props.charts?.workload ?? {
    labels: ['Clients', 'Projects', 'Tasks'],
    values: [props.stats.clients, props.stats.projects, props.stats.tasks],
  }
)

const tasksByAssignee = computed(() => props.charts?.tasksByAssignee ?? { labels: labels30, values: zeros12 })

const recent    = computed(() => props.recent    ?? [])
const deadlines = computed(() => props.deadlines ?? [])

const donutColors = ['#60A5FA', '#A78BFA', '#34D399', '#F59E0B', '#F472B6']
</script>
