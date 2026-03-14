<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

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
  filters?: { q?: string }
}>()

const routeGlobal = (window as any).route

const r = (name: string, params: any = {}, absolute = false, config?: any) => {
  if (typeof routeGlobal === 'function') {
    return routeGlobal(name, params, absolute, config)
  }
  return '#'
}

const org = computed(() => {
  if (props.organizationSlug) return props.organizationSlug
  try {
    const p = (routeGlobal as any)?.params ?? {}
    if (p.organization) return p.organization
  } catch { /* ignore */ }
  return 'acme'
})

const COLUMNS = [
  { key: 'lead', title: 'Lead', color: 'bg-sky-500/20 text-sky-300 border-sky-500/30' },
  { key: 'active', title: 'Active', color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' },
  { key: 'inactive', title: 'Inactive', color: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/30' },
  { key: 'paused', title: 'Paused', color: 'bg-amber-500/20 text-amber-300 border-amber-500/30' },
  { key: 'churned', title: 'Churned', color: 'bg-rose-500/20 text-rose-300 border-rose-500/30' },
] as const

const clientsByStatus = computed(() => {
  const map: Record<string, ClientLite[]> = {}
  for (const col of COLUMNS) {
    map[col.key] = []
  }
  for (const client of props.clients?.data ?? []) {
    const status = (client.status || 'lead').toLowerCase()
    if (map[status]) {
      map[status].push(client)
    } else {
      map.lead.push(client)
    }
  }
  return map
})

// --- Search ---
const searchQuery = ref(props.filters?.q ?? '')
let searchDebounce: ReturnType<typeof setTimeout> | null = null

watch(() => props.filters?.q, (v) => { searchQuery.value = v ?? '' })

function onSearchInput() {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    router.get(
      r('clients.pipeline', { organization: org.value }),
      { q: searchQuery.value || null },
      { preserveScroll: true, preserveState: true, replace: true },
    )
  }, 300)
}

function clearSearch() {
  searchQuery.value = ''
  router.get(
    r('clients.pipeline', { organization: org.value }),
    {},
    { preserveScroll: true, preserveState: true, replace: true },
  )
}

// --- Drag and drop with optimistic update ---
const dragClientId = ref<number | null>(null)
const dragOverColumn = ref<string | null>(null)

function startDrag(e: DragEvent, client: ClientLite) {
  if (!e.dataTransfer) return
  e.dataTransfer.dropEffect = 'move'
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', client.id.toString())
  dragClientId.value = client.id
}

function onDragEnd() {
  dragClientId.value = null
  dragOverColumn.value = null
}

function onDragOver(e: DragEvent, colKey: string) {
  e.preventDefault()
  dragOverColumn.value = colKey
}

function onDragLeave(colKey: string) {
  if (dragOverColumn.value === colKey) {
    dragOverColumn.value = null
  }
}

function drop(e: DragEvent, targetStatus: string) {
  dragOverColumn.value = null
  const id = Number(e.dataTransfer?.getData('text/plain') || 0)
  dragClientId.value = null
  if (!id) return

  const client = (props.clients?.data ?? []).find(c => c.id === id)
  if (!client || (client.status || 'lead').toLowerCase() === targetStatus) return

  const previousStatus = client.status
  client.status = targetStatus

  router.post(
    r('clients.pipeline.update', { organization: org.value, client: id }),
    { status: targetStatus },
    {
      preserveScroll: true,
      onError: () => { client.status = previousStatus },
    },
  )
}
</script>

<template>
  <PageShell
    :header="{
      breadcrumb: `Organization • ${String(org).toUpperCase()}`,
      title: 'Client Pipeline',
      subtitle: `${props.clients?.total ?? 0} clients across ${COLUMNS.length} stages. Drag cards to update status.`,
    }"
  >
    <template #header-actions>
      <div class="flex items-center gap-2">
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search clients…"
            class="rounded-full border border-white/15 bg-black/40 px-3 py-1.5 pl-8 text-xs text-white/80 placeholder-white/40 focus:border-emerald-400 focus:outline-none focus:ring-0 w-48"
            @input="onSearchInput"
          >
          <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <button
            v-if="searchQuery"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-xs"
            @click="clearSearch"
          >
            ✕
          </button>
        </div>
        <Link
          :href="r('clients.index', { organization: org })"
          class="inline-flex items-center rounded-full border border-white/20 bg-white/5 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-white/10"
        >
          List view
        </Link>
      </div>
    </template>

    <!-- Pipeline columns -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div
        v-for="col in COLUMNS"
        :key="col.key"
        class="rounded-2xl border border-white/10 bg-black/30 backdrop-blur min-h-[360px] flex flex-col transition-colors duration-150"
        :class="[dragOverColumn === col.key && 'border-emerald-400/50 bg-emerald-500/5']"
        @dragover="onDragOver($event, col.key)"
        @dragleave="onDragLeave(col.key)"
        @drop="drop($event, col.key)"
      >
        <!-- Column header -->
        <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
          <span
            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
            :class="col.color"
          >
            {{ col.title }}
          </span>
          <span class="text-[11px] text-white/40 font-medium">
            {{ clientsByStatus[col.key]?.length ?? 0 }}
          </span>
        </div>

        <!-- Cards -->
        <div class="flex-1 p-3 space-y-2 overflow-y-auto">
          <Link
            v-for="client in clientsByStatus[col.key]"
            :key="client.id"
            :href="r('clients.show', { organization: org, client: client.id })"
            class="block rounded-xl border border-white/10 bg-black/40 p-3 text-sm font-medium text-white shadow hover:border-white/20 hover:shadow-lg transition duration-150 cursor-move"
            :class="[dragClientId === client.id && 'opacity-40']"
            draggable="true"
            @dragstart="startDrag($event, client)"
            @dragend="onDragEnd"
          >
            {{ client.company_name }}
          </Link>

          <p
            v-if="(clientsByStatus[col.key]?.length ?? 0) === 0"
            class="px-1 py-6 text-center text-xs text-white/30"
          >
            No clients in {{ col.title.toLowerCase() }}.
          </p>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="props.clients?.links && props.clients.links.length > 3"
      class="border-t border-white/10 pt-4"
    >
      <nav class="flex flex-wrap items-center justify-end gap-1 text-xs">
        <template v-for="link in props.clients.links" :key="(link.url || '') + link.label">
          <button
            v-if="link.url"
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium"
            :class="[
              link.active
                ? 'bg-indigo-500 text-white'
                : 'bg-white/5 text-white/70 hover:bg-white/10',
            ]"
            v-html="link.label"
            @click="router.get(link.url!, {}, { preserveScroll: true, preserveState: true })"
          />
          <span
            v-else
            class="rounded-md px-3 py-1.5 text-xs text-white/30"
            v-html="link.label"
          />
        </template>
      </nav>
    </div>
  </PageShell>
</template>
