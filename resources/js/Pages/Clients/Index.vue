<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Set the layout for the page
defineOptions({ layout: AuthenticatedLayout })

const page = usePage<any>()

// --- Re-used Dashboard Logic for Routing ---
// Centralized route helper (Ziggy)
const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

// Props from Inertia
const props = defineProps<{
  organizationSlug: string
  filters: {
    status: string | null
    industry: string | null
    search: string | null
    q: string | null
  }
  clients: {
    data: Array<any>
    links: Array<{
      url: string | null
      label: string
      active: boolean
    }>
  }
}>()

// Reactive resolution of the organization slug
const org = computed(() => {
  return (
    props.organizationSlug ||
    (page.props as any).tenant?.slug ||
    (page.props as any).organization?.slug ||
    'acme'
  )
})

// Local filter state, hydrated from props.filters
const search = ref(props.filters.search ?? props.filters.q ?? '')
const status = ref(props.filters.status ?? '')
const industry = ref(props.filters.industry ?? '')

// Keep local state in sync when Inertia updates props
watch(
  () => props.filters,
  (f) => {
    search.value = f.search ?? f.q ?? ''
    status.value = f.status ?? ''
    industry.value = f.industry ?? ''
  },
)

// Options for the status filter (maps to `clients.status` column)
const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'lead', label: 'Lead' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'paused', label: 'Paused' },
  { value: 'churned', label: 'Churned' },
]

// Apply filters + search via Inertia GET
function applyFilters() {
  router.get(
    r('clients.index', { organization: org.value }),
    {
      search: search.value || null,
      status: status.value || null,
      industry: industry.value || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  )
}

function doSearch() {
  applyFilters()
}

function resetFilters() {
  search.value = ''
  status.value = ''
  industry.value = ''
  applyFilters()
}

// Helper function to apply the status chip styling
function getStatusClass(statusValue: string | null | undefined) {
  const base =
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '

  if (!statusValue) {
    return base + 'bg-gray-700/50 text-gray-300'
  }

  const statusLower = statusValue.toLowerCase()

  switch (statusLower) {
    case 'active':
      return base + 'bg-emerald-900/50 text-emerald-300'
    case 'inactive':
    case 'churned': // pipeline/status mapping
      return base + 'bg-red-900/50 text-red-300'
    case 'lead':
    case 'pending':
      return base + 'bg-yellow-900/50 text-yellow-300'
    case 'paused':
      return base + 'bg-gray-700/50 text-gray-300'
    default:
      return base + 'bg-gray-700/50 text-gray-300'
  }
}
</script>

<template>
  <div class="space-y-6">
    <section class="hero-slab">
      <div class="flex items-end justify-between gap-6">
        <div>
          <div class="text-sm text-white/60">
            Organization • {{ org.toUpperCase() }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Client Management
          </h1>
          <p class="mt-1 text-white/60">
            Search and manage your active and inactive clients.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="r('clients.pipeline', { organization: org })"
            class="btn-capsule text-sm"
          >
            Pipeline
          </Link>
          <Link
            :href="r('clients.create', { organization: org })"
            class="chip"
          >
            New Client
          </Link>
          <Link
            :href="r('clients.import', { organization: org })"
            class="btn-capsule text-sm"
          >
            Import CSV
          </Link>
          <Link
            :href="
              r('export.csv', {
                organization: org,
                entity: 'clients',
                include_deleted: 1,
              })
            "
            class="btn-capsule text-sm"
          >
            Export CSV
          </Link>
        </div>
      </div>
    </section>

    <section class="space-y-4">
      <!-- Filters Row -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="search"
            @keyup.enter="doSearch"
            placeholder="Search clients..."
            class="search-pill-lite"
          />
          <button @click="doSearch" class="btn-capsule text-sm">
            Search
          </button>
          <button
            v-if="search || status || industry"
            @click="resetFilters"
            class="text-xs text-white/60 hover:text-white underline-offset-2 hover:underline"
          >
            Reset
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <!-- Status filter -->
          <select
            v-model="status"
            @change="applyFilters"
            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/80 focus:outline-none focus:ring-1 focus:ring-[var(--primary)]"
          >
            <option
              v-for="opt in statusOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>

          <!-- Industry filter (exact match) -->
          <input
            v-model="industry"
            @keyup.enter="doSearch"
            placeholder="Industry"
            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/80 focus:outline-none focus:ring-1 focus:ring-[var(--primary)]"
          />
        </div>
      </div>

      <!-- Clients Table -->
      <div class="card-neo overflow-hidden">
        <table class="w-full text-left text-sm text-white">
          <thead class="border-b border-white/10 text-white/50">
            <tr>
              <th class="p-4">Company</th>
              <th class="p-4">Contact</th>
              <th class="p-4">Status</th>
              <th class="w-40 p-4"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-if="!props.clients.data.length"
              class="border-t border-white/10"
            >
              <td colspan="4" class="p-4 text-center text-sm text-white/60">
                No clients found for the current filters.
              </td>
            </tr>

            <tr
              v-for="c in props.clients.data"
              v-else
              :key="c.id"
              class="border-t border-white/10 transition duration-150 hover:bg-white/5"
            >
              <td class="p-4 font-medium">
                {{ c.company_name }}
              </td>
              <td class="p-4 text-white/70">
                {{ c.primary_contact_name }} · {{ c.primary_contact_email }}
              </td>
              <td class="p-4">
                <span :class="getStatusClass(c.client_activation_status)">
                  {{ c.client_activation_status || '—' }}
                </span>
              </td>
              <td class="p-4 text-right">
                <Link
                  :href="r('clients.show', { organization: org, client: c.id })"
                  class="text-indigo-400 hover:underline"
                >
                  Open
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div
          v-if="props.clients.links && props.clients.links.length > 1"
          class="border-t border-white/10 bg-black/20 px-4 py-3"
        >
          <nav class="flex flex-wrap items-center justify-end gap-1 text-xs">
            <Link
              v-for="link in props.clients.links"
              :key="link.label + (link.url || '')"
              :href="link.url || '#'"
              class="rounded-full px-3 py-1"
              :class="[
                link.active
                  ? 'bg-white/20 text-white'
                  : link.url
                    ? 'text-white/70 hover:bg-white/10'
                    : 'text-white/30 cursor-default',
              ]"
              v-html="link.label"
            />
          </nav>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Custom style for the search input to match the dark UI aesthetic */
.search-pill-lite {
  @apply w-72 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/80 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)];
}
</style>
