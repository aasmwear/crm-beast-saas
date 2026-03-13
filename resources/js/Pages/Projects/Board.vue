<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface BoardProject {
  id: number
  title: string
  status: string | null
  project_manager_id: number | null
  project_manager_name?: string | null
}
type PaginationLink = { url: string | null; label: string; active: boolean }
type PaginatedProjects = {
  data: BoardProject[]
  links: PaginationLink[]
}

const props = defineProps<{
  organization: { id: number; name: string; slug: string }
  projects: PaginatedProjects
}>()

const page = usePage()

const orgSlug = computed(() => {
  const p = page.props as any
  return props.organization?.slug ?? p?.tenant?.slug ?? p?.organization?.slug ?? 'acme'
})

const columns = [
  { key: 'planned', label: 'Planned' },
  { key: 'in-progress', label: 'In Progress' },
  { key: 'on-hold', label: 'On Hold' },
  { key: 'completed', label: 'Completed' },
  { key: 'cancelled', label: 'Cancelled' },
] as const

type ColumnKey = (typeof columns)[number]['key']

function normalizeStatus(status: string | null | undefined): ColumnKey {
  if (!status) return 'planned'
  const s = status.trim().toLowerCase()

  if (['planned'].includes(s)) return 'planned'
  if (['in progress', 'in_progress', 'active', 'ongoing'].includes(s)) return 'in-progress'
  if (['on hold', 'on_hold', 'paused'].includes(s)) return 'on-hold'
  if (['completed', 'done', 'finished', 'closed'].includes(s)) return 'completed'
  if (['cancelled', 'canceled'].includes(s)) return 'cancelled'

  return 'planned'
}

function projectsInColumn(key: ColumnKey): BoardProject[] {
  return (props.projects.data ?? []).filter((p) => normalizeStatus(p.status) === key)
}

const isUpdating = ref<number | null>(null)

function updateStatus(project: BoardProject, columnKey: ColumnKey) {
  const targetColumn = columns.find((c) => c.key === columnKey)
  if (!targetColumn) return

  const newStatus = targetColumn.label
  isUpdating.value = project.id

  router.post(
    route('projects.pipeline.update', {
      organization: orgSlug.value,
      project: project.id,
    }),
    { status: newStatus },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isUpdating.value = null
      },
    },
  )
}

function onColumnChange(project: BoardProject, event: Event) {
  const target = event.target as HTMLSelectElement
  const value = target.value as ColumnKey
  updateStatus(project, value)
}
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
            Project Board
          </h1>
          <p class="mt-1 text-sm text-white/60">
            Kanban view of your project pipeline grouped by status.
          </p>
        </div>

        <div class="inline-flex items-center gap-1 rounded-full bg-white/5 p-1 text-xs">
          <Link
            :href="route('projects.index', { organization: orgSlug })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            List
          </Link>
          <span
            class="rounded-full bg-[var(--primary)] px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow-[0_0_24px_rgba(139,124,255,0.65)]"
          >
            Board
          </span>
          <Link
            :href="route('projects.calendar', { organization: orgSlug })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            Calendar
          </Link>
        </div>
      </div>
    </div>

    <!-- Columns -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      <div
        v-for="column in columns"
        :key="column.key"
        class="glass flex min-h-[260px] flex-col rounded-2xl border border-white/5 bg-slate-950/40 p-3"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-100">
            {{ column.label }}
          </div>
          <div class="rounded-full bg-white/5 px-2 py-0.5 text-[10px] text-white/60">
            {{ projectsInColumn(column.key).length }}
          </div>
        </div>

        <div class="flex-1 space-y-2 overflow-y-auto pr-1">
          <div
            v-for="project in projectsInColumn(column.key)"
            :key="project.id"
            class="group rounded-xl border border-white/5 bg-white/5 p-3 text-sm transition hover:border-[var(--primary)] hover:bg-white/10"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="line-clamp-2 font-medium text-white">
                  {{ project.title }}
                </div>
                <div class="mt-1 text-[11px] text-white/50">
                  PM:
                  <span v-if="project.project_manager_name">
                    {{ project.project_manager_name }}
                  </span>
                  <span v-else>Unassigned</span>
                </div>
              </div>
            </div>

            <div class="mt-3 flex items-center justify-between gap-2">
              <div class="flex items-center gap-1">
                <div class="h-1.5 w-1.5 rounded-full bg-[var(--primary)]" />
                <div class="text-[10px] uppercase tracking-wide text-white/50">
                  {{ column.label }}
                </div>
              </div>

              <div class="flex items-center gap-1">
                <select
                  class="rounded-full border border-white/15 bg-slate-950/60 px-2 py-1 text-[10px] text-white/70 focus:border-[var(--primary)] focus:outline-none"
                  :value="column.key"
                  :disabled="isUpdating === project.id"
                  @change="onColumnChange(project, $event)"
                >
                  <option
                    v-for="target in columns"
                    :key="target.key"
                    :value="target.key"
                  >
                    {{ target.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div
            v-if="!projectsInColumn(column.key).length"
            class="mt-4 rounded-xl border border-dashed border-white/10 bg-slate-950/40 p-4 text-center text-xs text-white/40"
          >
            No projects in this stage yet.
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="props.projects.links && props.projects.links.length > 1"
      class="border-t border-white/10 pt-4"
    >
      <nav class="flex flex-wrap items-center justify-end gap-1 text-xs">
        <Link
          v-for="link in props.projects.links"
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
</template>
