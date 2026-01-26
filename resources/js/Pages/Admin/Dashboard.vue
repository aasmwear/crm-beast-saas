<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">Super Admin</h1>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <KpiTile label="Organizations" :value="stats.organizations" />
      <KpiTile label="Users"         :value="stats.users" />
      <KpiTile label="Clients"       :value="stats.clients" />
      <KpiTile label="Projects"      :value="stats.projects" />
      <KpiTile label="Tasks"         :value="stats.tasks" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card :title="spark.title">
        <MiniArea :labels="spark.labels" :values="spark.values" />
      </Card>

      <Card :title="`Projects by status`">
        <DonutChart
          :labels="projectsDonut.labels"
          :values="projectsDonut.values"
          :center="projectsDonut.center"
        />
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card :title="sparkUsers.title">
        <MiniArea :labels="sparkUsers.labels" :values="sparkUsers.values" />
      </Card>

      <Card title="Task flow (review status)">
        <DonutChart :labels="taskFlow.labels" :values="taskFlow.values" center="Tasks" />
      </Card>
    </div>

    <Card title="Recently Created Organizations">
      <div class="divide-y divide-white/10">
        <div v-for="org in recentOrgs" :key="org.id" class="py-3 flex items-center justify-between">
          <div>
            <div class="text-white/90 font-medium">{{ org.name }}</div>
            <div class="text-white/50 text-sm">/{{ org.slug }}</div>
          </div>
          <Link :href="`/org/${org.slug}/dashboard`" class="text-sm text-indigo-300 hover:text-indigo-200">
            View
          </Link>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import KpiTile   from '@/Components/ui/KpiTile.vue'
import Card      from '@/Components/ui/Card.vue'
import MiniArea  from '@/Components/charts/MiniArea.vue'
import DonutChart from '@/Components/charts/DonutChart.vue'

defineProps<{
  stats: { organizations:number; users:number; clients:number; projects:number; tasks:number }
  recentOrgs: Array<{ id:number; name:string; slug:string; created_at:string }>
  spark: { labels:string[]; values:number[]; title?:string }
  sparkUsers: { labels:string[]; values:number[]; title?:string }
  projectsDonut: { labels:string[]; values:number[]; center?:string }
  taskFlow: { labels:string[]; values:number[] }
}>()
</script>
