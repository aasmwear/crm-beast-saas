<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/ui/Card.vue'
import BarChart from '@/Components/charts/BarChart.vue'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps<{
  kpis: {
    total_tenants: number
    total_users: number
    active_tenants_30d: number
  }
  recent_tenants: Array<{
    id: number
    name: string
    slug: string
    created_at: string
    owner_email: string
  }>
  tenant_growth: { labels: string[]; values: number[] }
  system_status: string
}>()

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Hero: System Overview -->
    <section>
      <h1 class="text-3xl font-semibold tracking-tight text-white">
        System Overview
      </h1>
      <p class="mt-1 text-white/60">
        Global Cockpit · Monitor platform health across all tenants and users.
      </p>
    </section>

    <!-- KPI Cards -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card class="card-neo">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/20">
            <svg class="h-5 w-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div>
            <div class="text-xs text-white/50">
              Total Tenants
            </div>
            <div class="text-2xl font-semibold text-white">
              {{ kpis.total_tenants }}
            </div>
          </div>
        </div>
      </Card>

      <Card class="card-neo">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/20">
            <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
          <div>
            <div class="text-xs text-white/50">
              Total Users
            </div>
            <div class="text-2xl font-semibold text-white">
              {{ kpis.total_users }}
            </div>
          </div>
        </div>
      </Card>

      <Card class="card-neo">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20">
            <svg class="h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <div class="text-xs text-white/50">
              Active Tenants (30d)
            </div>
            <div class="text-2xl font-semibold text-white">
              {{ kpis.active_tenants_30d }}
            </div>
          </div>
        </div>
      </Card>

      <Card class="card-neo">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20">
            <span class="inline-flex items-center rounded-full bg-emerald-900/50 px-2.5 py-0.5 text-xs font-medium text-emerald-300">
              Operational
            </span>
          </div>
          <div>
            <div class="text-xs text-white/50">
              System Status
            </div>
            <div class="text-lg font-semibold text-emerald-400">
              Operational
            </div>
          </div>
        </div>
      </Card>
    </section>

    <!-- Tenant Growth Chart -->
    <section>
      <Card title="New Tenants" class="card-neo">
        <BarChart
          :labels="tenant_growth.labels"
          :values="tenant_growth.values"
        />
      </Card>
    </section>

    <!-- Recent Tenants Table -->
    <section>
      <Card title="Recent Tenants" class="card-neo">
        <div v-if="recent_tenants.length > 0" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-white/50 border-b border-white/10">
                <th class="pb-2 pr-3">Name</th>
                <th class="pb-2 pr-3">Slug</th>
                <th class="pb-2 pr-3">Created At</th>
                <th class="pb-2 pr-3">Owner Email</th>
                <th class="pb-2 w-28 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="org in recent_tenants"
                :key="org.id"
                class="border-b border-white/5"
              >
                <td class="py-3 pr-3 font-medium text-white">
                  {{ org.name }}
                </td>
                <td class="py-3 pr-3 text-white/70">
                  /{{ org.slug }}
                </td>
                <td class="py-3 pr-3 text-white/70">
                  {{ formatDate(org.created_at) }}
                </td>
                <td class="py-3 pr-3 text-white/70">
                  {{ org.owner_email }}
                </td>
                <td class="py-3 text-right">
                  <Link
                    :href="`/org/${org.slug}/dashboard`"
                    class="text-sm text-indigo-300 hover:text-indigo-200"
                  >
                    View
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-8 text-center text-white/50">
          No tenants yet.
        </p>
      </Card>
    </section>
  </div>
</template>
