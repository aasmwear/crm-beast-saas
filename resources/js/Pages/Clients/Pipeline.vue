<script setup lang="ts">
import { computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

type ClientLite = {
  id: number
  company_name: string
  status: string | null
}

type PaginationLink = { url: string | null; label: string; active: boolean }
type PaginatedClients = {
  data: ClientLite[]
  links: PaginationLink[]
  total?: number
}

const props = defineProps<{
  organizationSlug: string
  clients: PaginatedClients
}>()

/** Client rows for current page (from paginated payload). */
const clientRows = computed(() => props.clients?.data ?? [])

// --- SAFE ROUTE LOGIC ---
const routeGlobal = (window as any).route

const r = (name: string, params: any = {}, absolute = false, config?: any) => {
  if (typeof routeGlobal === 'function') {
    return routeGlobal(name, params, absolute, config)
  }
  return '#'
}

const org = computed(() => {
  // Prefer explicit slug from backend
  if (props.organizationSlug) {
    return props.organizationSlug
  }

  // Fallback: try to read from Ziggy's stored params
  try {
    const p = (routeGlobal as any)?.params ?? {}
    if (p.organization) return p.organization
  } catch {
    // ignore
  }

  // Final fallback for local/dev
  return 'acme'
})
// -----------------------

const cols = [
  { key: 'lead', title: 'Lead' },
  { key: 'active', title: 'Active' },
  { key: 'paused', title: 'Paused' },
  { key: 'churned', title: 'Churned' },
]

function byCol(key: string) {
  return clientRows.value.filter(
    (c) => (c.status || 'lead').toLowerCase() === key,
  )
}

function startDrag(e: DragEvent, id: number) {
  if (!e.dataTransfer) return
  e.dataTransfer.dropEffect = 'move'
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', id.toString())
}

function drop(e: DragEvent, status: string) {
  const id = Number(e.dataTransfer?.getData('text/plain') || 0)
  if (!id) return

  router.post(
    r('clients.pipeline.update', {
      organization: org.value,
      client: id,
    }),
    { status },
    { preserveScroll: true },
  )
}

const clientCardClass =
  'card-neo-small text-white p-3 rounded-xl border border-white/10 shadow hover:shadow-xl transition duration-150 cursor-move'
</script>

<template>
  <div>
    <div class="mb-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-white">
            Client Pipeline
          </h1>
          <p class="text-white/60 mt-1">
            Drag &amp; drop cards across stages to update status.
          </p>
        </div>
        <Link
          :href="r('clients.index', { organization: org })"
          class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
        >
          View as List
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div
        v-for="c in cols"
        :key="c.key"
        class="card-neo rounded-xl p-4 min-h-[400px]"
        @dragover.prevent
        @drop="drop($event, c.key)"
      >
        <div
          class="text-lg font-medium text-white mb-4 border-b border-white/10 pb-2"
        >
          {{ c.title }}
          <span class="text-white/50 text-sm ml-1">
            ({{ byCol(c.key).length }})
          </span>
        </div>

        <div class="space-y-3">
          <Link
            v-for="cl in byCol(c.key)"
            :key="cl.id"
            :href="
              r('clients.show', {
                organization: org,
                client: cl.id,
              })
            "
            :class="clientCardClass"
            draggable="true"
            @dragstart="startDrag($event, cl.id)"
          >
            <div class="font-medium text-white">
              {{ cl.company_name }}
            </div>
          </Link>

          <p
            v-if="byCol(c.key).length === 0"
            class="text-sm text-white/50 pt-2"
          >
            No clients in this stage.
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="props.clients?.links && props.clients.links.length > 1"
      class="mt-6 border-t border-white/10 pt-4"
    >
      <nav class="flex flex-wrap items-center justify-end gap-1 text-xs">
        <Link
          v-for="link in props.clients.links"
          :key="(link.url || '') + link.label"
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
</template>

<style scoped>
.card-neo-small {
  /* Subtle neon border on hover */
  box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
}
.card-neo-small:hover {
  box-shadow: 0 0 0 1px var(--primary);
}
</style>
