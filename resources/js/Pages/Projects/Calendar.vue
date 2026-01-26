<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface CalendarEvent {
  id: number
  title: string
  status: string | null
  start_date: string | null
  end_date: string | null
  project_manager_name?: string | null
}

interface EventGroup {
  label: string
  sortKey: number
  items: CalendarEvent[]
}

const props = defineProps<{
  organization: { id: number; name: string; slug: string }
  filters: { from: string | null; to: string | null }
  events: CalendarEvent[]
}>()

const page = usePage()

const orgSlug = computed(() => {
  const p = page.props as any
  return props.organization?.slug ?? p?.tenant?.slug ?? p?.organization?.slug ?? 'acme'
})

const localFilters = ref({
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
})

function applyFilters() {
  router.get(
    route('projects.calendar', { organization: orgSlug.value }),
    {
      from: localFilters.value.from || undefined,
      to: localFilters.value.to || undefined,
    },
    {
      preserveScroll: true,
      preserveState: true,
    },
  )
}

function clearFilters() {
  localFilters.value.from = ''
  localFilters.value.to = ''
  applyFilters()
}

function formatDateLabel(dateStr: string | null | undefined): string {
  if (!dateStr) return 'No date'
  const d = new Date(`${dateStr}T00:00:00`)
  if (Number.isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  })
}

const groupedEvents = computed<EventGroup[]>(() => {
  const map = new Map<string, EventGroup>()

  props.events.forEach((e) => {
    const baseDateStr = e.start_date ?? e.end_date
    let label: string
    let sortKey: number

    if (baseDateStr) {
      const d = new Date(`${baseDateStr}T00:00:00`)
      const year = d.getFullYear()
      const monthIndex = d.getMonth()
      const month = d.toLocaleString(undefined, { month: 'short' })
      label = `${month} ${year}`
      sortKey = year * 12 + monthIndex
    } else {
      label = 'No Date'
      sortKey = Number.MAX_SAFE_INTEGER
    }

    let group = map.get(label)
    if (!group) {
      group = { label, sortKey, items: [] }
      map.set(label, group)
    }
    group.items.push(e)
  })

  return Array.from(map.values()).sort((a, b) => a.sortKey - b.sortKey)
})

const totalEvents = computed(() => props.events.length)
</script>

<template>
  <div class="space-y-6">
    <!-- Header / Tabs -->
    <div
      class="mb-2 rounded-2xl bg-gradient-to-br from-[rgba(13,15,18,0.9)] via-[rgba(18,18,40,0.85)] to-transparent border border-white/5 px-5 py-4"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-lg font-semibold tracking-tight text-white">
            Projects Calendar
          </h1>
          <p class="mt-1 text-sm text-white/60">
            Timeline of projects by start and end dates.
          </p>
        </div>

        <div class="inline-flex items-center gap-1 rounded-full bg-white/5 p-1 text-xs">
          <Link
            :href="route('projects.index', { organization: orgSlug })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            List
          </Link>
          <Link
            :href="route('projects.board', { organization: orgSlug })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            Board
          </Link>
          <span
            class="rounded-full bg-[var(--primary)] px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow-[0_0_24px_rgba(139,124,255,0.65)]"
          >
            Calendar
          </span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div
      class="flex flex-col gap-3 rounded-2xl border border-white/5 bg-slate-950/40 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="flex flex-wrap items-center gap-3 text-xs text-white/70">
        <div class="flex items-center gap-2">
          <span class="text-[11px] uppercase tracking-wide text-white/50">From</span>
          <input
            v-model="localFilters.from"
            type="date"
            class="rounded-md border border-white/10 bg-slate-950/70 px-2 py-1 text-xs text-white focus:border-[var(--primary)] focus:outline-none"
          />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[11px] uppercase tracking-wide text-white/50">To</span>
          <input
            v-model="localFilters.to"
            type="date"
            class="rounded-md border border-white/10 bg-slate-950/70 px-2 py-1 text-xs text-white focus:border-[var(--primary)] focus:outline-none"
          />
        </div>
        <div class="hidden text-[11px] text-white/40 sm:inline">
          Showing {{ totalEvents }} project<span v-if="totalEvents !== 1">s</span>
        </div>
      </div>

      <div class="flex items-center gap-2 text-xs">
        <button
          type="button"
          class="rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-wide text-white/80 hover:bg-white/10"
          @click="applyFilters"
        >
          Apply
        </button>
        <button
          type="button"
          class="rounded-full border border-white/10 bg-transparent px-3 py-1 text-[11px] uppercase tracking-wide text-white/50 hover:bg-white/5"
          @click="clearFilters"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Timeline -->
    <div class="glass rounded-2xl border border-white/5 bg-slate-950/40 p-4">
      <div class="mb-3 flex items-center justify-between text-xs text-white/60">
        <div class="font-semibold uppercase tracking-wide">
          Timeline
        </div>
        <div>
          {{ totalEvents }} project<span v-if="totalEvents !== 1">s</span> in range
        </div>
      </div>

      <div
        v-if="!groupedEvents.length"
        class="rounded-xl border border-dashed border-white/10 bg-slate-950/60 p-6 text-center text-sm text-white/40"
      >
        No projects found for the selected date range.
      </div>

      <div v-else class="space-y-6">
        <div
          v-for="group in groupedEvents"
          :key="group.label"
          class="space-y-3"
        >
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-gradient-to-r from-[var(--primary)]/70 via-white/20 to-transparent" />
            <div
              class="rounded-full border border-white/10 bg-slate-950/80 px-3 py-1 text-[11px] uppercase tracking-wide text-white/80"
            >
              {{ group.label }}
            </div>
            <div class="h-px flex-1 bg-gradient-to-l from-[var(--primary)]/70 via-white/20 to-transparent" />
          </div>

          <div class="space-y-2">
            <div
              v-for="event in group.items"
              :key="event.id"
              class="relative flex items-start gap-3 rounded-xl border border-white/5 bg-white/5 p-3 text-xs text-white/80"
            >
              <div class="mt-1 flex flex-col items-center gap-1">
                <div class="h-2 w-2 rounded-full bg-[var(--primary)]" />
                <div class="h-full w-px flex-1 bg-gradient-to-b from-[var(--primary)]/70 via-white/20 to-transparent" />
              </div>

              <div class="flex-1 space-y-1">
                <div class="text-sm font-medium text-white">
                  {{ event.title }}
                </div>
                <div class="text-[11px] text-white/60">
                  <span v-if="event.start_date && event.end_date">
                    {{ formatDateLabel(event.start_date) }} → {{ formatDateLabel(event.end_date) }}
                  </span>
                <span v-else-if="event.start_date">
                    Starts {{ formatDateLabel(event.start_date) }}
                  </span>
                  <span v-else-if="event.end_date">
                    Due {{ formatDateLabel(event.end_date) }}
                  </span>
                  <span v-else>
                    No dates set
                  </span>
                </div>
                <div class="text-[11px] text-white/50">
                  PM:
                  <span v-if="event.project_manager_name">
                    {{ event.project_manager_name }}
                  </span>
                  <span v-else>
                    Unassigned
                  </span>
                </div>
              </div>

              <div class="ml-2 flex flex-col items-end gap-1">
                <div
                  class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] uppercase tracking-wide text-white/70"
                >
                  {{ event.status || 'No Status' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
