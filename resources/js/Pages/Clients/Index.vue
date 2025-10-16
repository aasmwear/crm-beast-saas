<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { route as ziggyRoute } from '@ziggy'

type ClientRow = {
  id: number | string
  company_name: string
  niche?: string | null
  status?: string | null
}

const props = defineProps<{
  // Inertia paginated resource
  clients: {
    data: ClientRow[]
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: { q?: string | null; status?: string | null; industry?: string | null }
  // Optional – if the controller provides it
  organization?: { slug: string }
}>()

// Robust org slug (prop -> Ziggy current URL -> pathname)
const orgSlug = computed<string>(() => {
  if (props.organization?.slug) return props.organization.slug
  // URL like: /org/{slug}/clients
  const path = (usePage().url || window.location.pathname || '').split('/').filter(Boolean)
  const idx = path.indexOf('org')
  return idx >= 0 && path[idx + 1] ? path[idx + 1] : ''
})

// Form state (preserve querystring)
const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? '')
const industry = ref(props.filters.industry ?? '')

const apply = () => {
  router.get(
    ziggyRoute('clients.index', { organization: orgSlug.value }),
    {
      q: q.value || undefined,
      status: status.value || undefined,
      industry: industry.value || undefined,
    },
    { preserveState: true, replace: true }
  )
}

// Debounce search typing a bit
let t: number | undefined
watch([q, status, industry], () => {
  if (t) window.clearTimeout(t)
  t = window.setTimeout(() => apply(), 250)
})

// Helpers to build links safely
const showHref = (id: number | string) =>
  ziggyRoute('clients.show', { organization: orgSlug.value, client: id })

const pageHref = (rawUrl: string | null) => rawUrl ?? '#'
</script>

<template>
  <Head title="Clients" />

  <div class="p-6 space-y-6">
    <!-- Filters -->
    <form class="grid gap-3 md:grid-cols-4" @submit.prevent="apply">
      <input
        v-model="q"
        type="search"
        placeholder="Search company / industry / niche / contact…"
        class="border rounded px-3 py-2 w-full"
      />
      <select v-model="status" class="border rounded px-3 py-2 w-full">
        <option value="">Status: Any</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <input
        v-model="industry"
        type="text"
        placeholder="Industry"
        class="border rounded px-3 py-2 w-full"
      />
      <button type="submit" class="rounded px-3 py-2 border">Apply</button>
    </form>

    <!-- List -->
    <div class="overflow-x-auto border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-4 py-2">Company</th>
            <th class="text-left px-4 py-2">Niche</th>
            <th class="text-left px-4 py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in props.clients.data" :key="c.id" class="border-t">
            <td class="px-4 py-2">
              <Link :href="showHref(c.id)" class="text-blue-600 hover:underline">
                {{ c.company_name }}
              </Link>
            </td>
            <td class="px-4 py-2">{{ c.niche || '—' }}</td>
            <td class="px-4 py-2">
              <span
                class="inline-block px-2 py-0.5 rounded text-xs"
                :class="c.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
              >
                {{ c.status || '—' }}
              </span>
            </td>
          </tr>
          <tr v-if="props.clients.data.length === 0">
            <td colspan="3" class="px-4 py-6 text-center text-gray-500">No clients found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav class="flex gap-1 flex-wrap">
      <Link
        v-for="l in props.clients.links"
        :key="l.label + (l.url || '')"
        :href="pageHref(l.url)"
        :class="[
          'px-3 py-1 rounded border',
          l.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white',
          !l.url ? 'opacity-50 pointer-events-none' : '',
        ]"
        v-html="l.label"
      />
    </nav>
  </div>
</template>
