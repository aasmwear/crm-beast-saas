<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import Card from '@/Components/ui/Card.vue'
import KpiTile from '@/Components/ui/KpiTile.vue'
import DonutChart from '@/Components/ui/DonutChart.vue'
import SparkLine from '@/Components/ui/SparkLine.vue'
import { computed } from 'vue'


type PageProps = { auth?: { user?: { name?: string } } }
const page = usePage<PageProps>()
const userName = computed(() => page.props.auth?.user?.name ?? 'Owner')

type KPI = {
  activeProjects: number
  overdueTasks: number
  myOverdueTasks: number
  myOpenNext7Days: number
  myCompleted7: number
  myCompleted30: number
}

const props = defineProps<{
  tenant: { id:number; name:string; slug:string } | null
  kpis: KPI
  myClients: Array<{ id:number; company_name:string; niche:string; status:string }>
  activity: Array<{ id:number; title:string; status:string; ts:string; project_title:string; client_name:string }>
  announcements: Array<{ id:number; title:string; body:string; created_at:string }>
  // optional chart data from server (safe fallbacks)
  taskTrend7?: number[]
  statusSplit?: { open:number; overdue:number; done:number }
}>()

// Fallbacks if server didn’t send chart arrays yet
const trend = props.taskTrend7 && props.taskTrend7.length ? props.taskTrend7 : [4,5,6,4,7,6,8]
const split = props.statusSplit ?? { open: props.kpis.activeProjects || 1, overdue: props.kpis.overdueTasks || 0, done: props.kpis.myCompleted7 || 0 }
const donutSeries = [
  { label: 'Open', value: split.open },
  { label: 'Overdue', value: split.overdue },
  { label: 'Done', value: split.done },
]
</script>

<template>
  <Head title="Dashboard" />
  <div class="min-h-screen bg-[#121216] text-[#EDEEF6]">
    <!-- Header bar -->
    <div class="px-6 pt-6 pb-4">
      <div class="text-sm text-[#A9ABB8]">Dashboard / Overview</div>
      <div class="mt-2 text-3xl font-semibold">Hello, {{ userName }} <span class="inline-block">👋</span></div>
      <div class="text-[#A9ABB8] mt-1">Let’s check how your workspace is performing today.</div>
    </div>

    <!-- Main grid -->
    <div class="px-6 grid grid-cols-12 gap-6">
      <!-- Left: Hero + tiles + table -->
      <div class="col-span-12 xl:col-span-8 space-y-6">
        <!-- Hero card -->
        <Card class="relative overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-tr from-[#2b2c36] via-[#1A1B22] to-[#2a2359] opacity-90"></div>
          <div class="relative p-6">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm text-[#A9ABB8]">This Week</div>
                <div class="mt-1 text-4xl font-bold tracking-tight">{{ kpis.activeProjects }} Active Projects</div>
                <div class="mt-1 text-[#A9ABB8]">You’re doing great! Keep it up.</div>
              </div>
              <div class="hidden md:flex items-center gap-2">
                <button class="px-3 py-2 rounded-xl bg-[#20222A] border border-[#3A3A42]">Filter</button>
                <button class="px-3 py-2 rounded-xl bg-[#8B7CFF] text-black font-medium">Export</button>
              </div>
            </div>

            <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
              <KpiTile label="Overdue (All)" :value="kpis.overdueTasks" />
              <KpiTile label="My Overdue" :value="kpis.myOverdueTasks" />
              <KpiTile label="My Next 7 Days" :value="kpis.myOpenNext7Days" />
              <KpiTile label="Completed (7d)" :value="kpis.myCompleted7" trend="+%" />
            </div>
          </div>
        </Card>

        <!-- Recent Activity -->
        <Card>
          <div class="p-6">
            <div class="flex items-center justify-between">
              <div class="text-lg font-semibold">Recent Activity</div>
              <div class="text-sm text-[#A9ABB8]">{{ activity.length }} events</div>
            </div>

            <div v-if="activity.length" class="mt-4 divide-y divide-[#3A3A42]">
              <div v-for="a in activity" :key="a.id" class="py-3 flex items-center justify-between">
                <div>
                  <div class="font-medium">{{ a.title }}</div>
                  <div class="text-xs text-[#A9ABB8]">{{ a.project_title }} • {{ a.client_name }}</div>
                </div>
                <div class="text-sm text-[#A9ABB8]">{{ new Date(a.ts).toLocaleString() }}</div>
              </div>
            </div>
            <div v-else class="mt-4 text-[#A9ABB8]">Nothing yet.</div>
          </div>
        </Card>

        <!-- Transactions-style table (Projects/Tasks Overview) -->
        <Card>
          <div class="p-6">
            <div class="flex items-center justify-between">
              <div class="text-lg font-semibold">Trending (7 days)</div>
            </div>
            <div class="mt-4">
              <SparkLine :data="trend" class="h-28 w-full" />
            </div>
          </div>
        </Card>
      </div>

      <!-- Right: donut + small cards -->
      <div class="col-span-12 xl:col-span-4 space-y-6">
        <Card>
          <div class="p-6">
            <div class="text-lg font-semibold">Workload Overview</div>
            <div class="mt-4 flex items-center gap-6">
              <DonutChart :series="donutSeries" size="140" />
              <div class="space-y-2">
                <div v-for="s in donutSeries" :key="s.label" class="flex items-center gap-2">
                  <span class="inline-block h-2 w-2 rounded-full bg-white/80"></span>
                  <span class="text-sm text-[#A9ABB8] w-24">{{ s.label }}</span>
                  <span class="font-medium">{{ s.value }}</span>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <Card>
          <div class="p-6">
            <div class="text-lg font-semibold">Pinned</div>
            <div v-if="announcements.length" class="mt-4 space-y-3">
              <div v-for="p in announcements" :key="p.id">
                <div class="font-medium">{{ p.title }}</div>
                <div class="text-sm text-[#A9ABB8] line-clamp-2" v-html="p.body" />
              </div>
            </div>
            <div v-else class="mt-4 text-sm text-[#A9ABB8]">No announcements.</div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
