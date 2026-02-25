<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const r = (name: string, params: Record<string, unknown> = {}) =>
  // @ts-ignore Ziggy
  (window as any).route(name, params)

const props = defineProps<{
  organization: { id: number; name: string; slug: string }
  stats?: {
    clients: number
    projects: number
    tasks: number
    attendance: number
    invoices: number
  }
  canExport?: boolean
}>()

const stats = computed(() => props.stats ?? {
  clients: 0,
  projects: 0,
  tasks: 0,
  attendance: 0,
  invoices: 0,
})

const orgSlug = computed(() => props.organization?.slug ?? 'acme')

const cards = computed(() => [
  {
    key: 'clients',
    label: 'Clients',
    icon: '👥',
    count: stats.value.clients,
    href: r('clients.index', { organization: orgSlug.value }),
    description: 'Client list and pipeline',
  },
  {
    key: 'projects',
    label: 'Projects',
    icon: '📁',
    count: stats.value.projects,
    href: r('projects.index', { organization: orgSlug.value }),
    description: 'Project status and financials',
  },
  {
    key: 'tasks',
    label: 'Tasks',
    icon: '✅',
    count: stats.value.tasks,
    href: r('tasks.index', { organization: orgSlug.value }),
    description: 'Task board and list',
  },
  {
    key: 'attendance',
    label: 'Attendance',
    icon: '⏰',
    count: stats.value.attendance,
    href: r('attendance.index', { organization: orgSlug.value }),
    description: 'Clock-in / clock-out records',
  },
  {
    key: 'invoices',
    label: 'Invoices',
    icon: '📄',
    count: stats.value.invoices,
    href: r('invoices.index', { organization: orgSlug.value }),
    description: 'Invoices and billing',
  },
])
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
      <div>
        <h1 class="text-xl font-semibold text-white">
          Reports
        </h1>
        <p class="mt-1 text-sm text-white/60">
          Overview and quick access to
          <span class="font-medium text-white">{{ organization?.name }}</span>
          data. Export clients to CSV below.
        </p>
      </div>
    </div>

    <!-- Quick cards -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
      <Link
        v-for="card in cards"
        :key="card.key"
        :href="card.href"
        class="group overflow-hidden rounded-2xl border border-white/10 bg-black/40 p-5 backdrop-blur transition hover:border-white/20 hover:bg-black/50"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-white/50">
              {{ card.label }}
            </p>
            <p class="mt-1 text-2xl font-semibold text-white">
              {{ card.count }}
            </p>
            <p class="mt-1 text-xs text-white/50">
              {{ card.description }}
            </p>
          </div>
          <span class="text-2xl opacity-70 group-hover:opacity-100">{{ card.icon }}</span>
        </div>
      </Link>
    </section>

    <!-- Export section -->
    <section class="overflow-hidden rounded-2xl border border-white/10 bg-black/40 p-6 backdrop-blur">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-sm font-semibold text-white">
            Export clients
          </h2>
          <p class="mt-1 text-xs text-white/50">
            Download client list as CSV (company, primary contact email, primary contact phone).
          </p>
        </div>
        <div v-if="canExport" class="flex items-center gap-3">
          <a
            :href="r('export.csv', { organization: orgSlug, entity: 'clients' })"
            target="_blank"
            class="inline-flex items-center rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20"
          >
            Export CSV
          </a>
          <a
            :href="r('export.csv', { organization: orgSlug, entity: 'clients' }) + '?include_deleted=1'"
            class="inline-flex items-center rounded-lg border border-white/10 px-4 py-2 text-sm text-white/70 hover:text-white"
          >
            Include deleted
          </a>
        </div>
        <p v-else class="text-xs text-white/50">
          You need reports.export permission to download.
        </p>
      </div>
    </section>

    <!-- Placeholder for future reports -->
    <section class="overflow-hidden rounded-2xl border border-white/10 bg-black/30 p-6 backdrop-blur">
      <p class="text-sm text-white/50">
        Deeper reports (revenue by period, task completion rates, attendance summaries) are planned. Use the cards above to explore data in each module.
      </p>
    </section>
  </div>
</template>
