<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'
import EmptyState from '@/Components/ui/EmptyState.vue'
import TaskDrawer from '@/Components/tasks/TaskDrawer.vue'

defineOptions({ layout: AuthenticatedLayout })

interface ProjectSummary {
  id: number
  title: string
}

interface Task {
  id: number
  title: string
  status?: string | null
  priority?: string | null
  due_date?: string | null
  project?: ProjectSummary | null
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedTasks {
  data: Task[]
  links: PaginationLink[]
}

interface Filters {
  status?: string | null
  mine?: boolean
  project_id?: string | number | null
  due_from?: string | null
  due_to?: string | null
  search?: string | null
}

const page = usePage<any>()

const props = defineProps<{
  tasks: PaginatedTasks
  filters?: Filters
  projects?: ProjectSummary[]
  canCreate?: boolean
}>()

// Resolve org slug from shared Inertia props (tenant-aware)
const org = computed(() => {
  const propsAny = page.props as any

  return (
    propsAny.tenant?.slug ??
    propsAny.organization?.slug ??
    propsAny.organizationSlug ??
    'acme'
  )
})

// Ziggy route helper from window.route
const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

// Local filter state
const statusFilter = ref<string>(props.filters?.status ?? '')
const mineFilter = ref<boolean>(props.filters?.mine ?? false)
const projectFilter = ref<string | null>(
  props.filters?.project_id !== undefined && props.filters?.project_id !== null
    ? String(props.filters.project_id)
    : '',
)
const dueFromFilter = ref<string>(props.filters?.due_from ?? '')
const dueToFilter = ref<string>(props.filters?.due_to ?? '')
const search = ref<string>(props.filters?.search ?? '')

const hasFilters = computed(() =>
  !!(
    statusFilter.value ||
    mineFilter.value ||
    projectFilter.value ||
    dueFromFilter.value ||
    dueToFilter.value ||
    search.value
  ),
)

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'Todo', label: 'Todo' },
  { value: 'In Progress', label: 'In progress' },
  { value: 'Submitted', label: 'Submitted' },
  { value: 'Approved', label: 'Approved' },
  { value: 'Changes Requested', label: 'Changes requested' },
  { value: 'Rejected', label: 'Rejected' },
  { value: 'Done', label: 'Done' },
]

// Apply filters via Inertia GET
function applyFilters() {
  router.get(
    r('tasks.index', { organization: org.value }),
    {
      status: statusFilter.value || null,
      mine: mineFilter.value ? 1 : null,
      project_id: projectFilter.value || null,
      due_from: dueFromFilter.value || null,
      due_to: dueToFilter.value || null,
      search: search.value || null,
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
  statusFilter.value = ''
  mineFilter.value = false
  projectFilter.value = ''
  dueFromFilter.value = ''
  dueToFilter.value = ''
  search.value = ''
  applyFilters()
}

function statusClass(status?: string | null): string {
  const value = (status ?? '').toLowerCase().trim()
  const base =
    'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium '

  if (!value) {
    return base + 'border-white/10 bg-white/5 text-white/60'
  }

  if (['todo', 'backlog', 'open', 'new'].includes(value)) {
    return base + 'border-sky-500/40 bg-sky-500/15 text-sky-200'
  }

  if (['in progress', 'doing', 'active'].includes(value)) {
    return base + 'border-amber-400/40 bg-amber-500/15 text-amber-200'
  }

  if (['submitted', 'pending review'].includes(value)) {
    return base + 'border-indigo-400/40 bg-indigo-500/15 text-indigo-200'
  }

  if (['approved', 'done', 'completed'].includes(value)) {
    return base + 'border-emerald-400/40 bg-emerald-500/15 text-emerald-200'
  }

  if (['changes requested', 'rejected', 'blocked'].includes(value)) {
    return base + 'border-red-400/40 bg-red-500/15 text-red-200'
  }

  return base + 'border-white/10 bg-white/5 text-white/70'
}

function priorityClass(priority?: string | null): string {
  const value = (priority ?? '').toLowerCase().trim()

  if (!value) {
    return 'text-white/60'
  }

  if (['low'].includes(value)) return 'text-emerald-300'
  if (['medium', 'normal'].includes(value)) return 'text-amber-300'
  if (['high', 'urgent'].includes(value)) return 'text-red-300'

  return 'text-white/60'
}

function formatDate(value?: string | null): string {
  if (!value) return '—'

  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'

  return d.toLocaleDateString(undefined, {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  })
}

// Drawer state
const drawerOpen = ref(false)
const drawerTaskId = ref<number | null>(null)

function openTask(taskId: number) {
  drawerTaskId.value = taskId
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}
</script>

<template>
  <PageShell
    :header="{
      breadcrumb: `Organization • ${String(org).toUpperCase()}`,
      title: 'Tasks',
      subtitle: 'Browse and filter tasks across all projects in this organization.',
    }"
  >
    <template #header-actions>
      <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex items-center gap-1 rounded-full bg-white/5 p-1 text-xs">
          <span
            class="rounded-full bg-[var(--primary)] px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow-[0_0_24px_rgba(139,124,255,0.65)]"
          >
            List
          </span>
          <Link
            :href="r('tasks.board', { organization: org })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            Board
          </Link>
        </div>

        <Link
          :href="r('projects.index', { organization: org })"
          class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10"
        >
          View projects
        </Link>
        <Link
          v-if="canCreate !== false"
          :href="r('projects.index', { organization: org })"
          class="inline-flex items-center gap-2 rounded-full bg-[var(--primary)] px-4 py-1.5 text-xs font-semibold text-white hover:opacity-90"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Task
        </Link>
      </div>
    </template>

    <div class="max-w-6xl mx-auto space-y-6">
    <!-- Filters -->
    <div class="rounded-2xl border border-white/10 bg-slate-950/80 p-4 space-y-4">
      <div class="grid gap-3 md:grid-cols-4">
        <!-- Search -->
        <div class="space-y-1">
          <label
            for="task-search"
            class="block text-[11px] font-semibold uppercase tracking-wide text-white/50"
          >
            Search
          </label>
          <input
            id="task-search"
            v-model="search"
            type="text"
            class="w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-1.5 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
            placeholder="Search by title"
            @keyup.enter="doSearch"
          >
        </div>

        <!-- Status -->
        <div class="space-y-1">
          <label
            for="task-status"
            class="block text-[11px] font-semibold uppercase tracking-wide text-white/50"
          >
            Status
          </label>
          <select
            id="task-status"
            v-model="statusFilter"
            class="w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-1.5 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
            @change="applyFilters"
          >
            <option
              v-for="opt in statusOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Project -->
        <div class="space-y-1">
          <label
            for="task-project"
            class="block text-[11px] font-semibold uppercase tracking-wide text-white/50"
          >
            Project
          </label>
          <select
            id="task-project"
            v-model="projectFilter"
            class="w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-1.5 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
            @change="applyFilters"
          >
            <option value="">
              All projects
            </option>
            <option
              v-for="project in props.projects ?? []"
              :key="project.id"
              :value="String(project.id)"
            >
              {{ project.title }}
            </option>
          </select>
        </div>

        <!-- Mine only -->
        <div class="flex items-end">
          <label class="inline-flex items-center gap-2 text-xs text-white/70">
            <input
              v-model="mineFilter"
              type="checkbox"
              class="h-4 w-4 rounded border-white/20 bg-slate-950 text-indigo-400 focus:ring-indigo-400/60"
              @change="applyFilters"
            >
            <span>Only my tasks</span>
          </label>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <!-- Due from -->
        <div class="space-y-1">
          <label
            for="task-due-from"
            class="block text-[11px] font-semibold uppercase tracking-wide text-white/50"
          >
            Due from
          </label>
          <input
            id="task-due-from"
            v-model="dueFromFilter"
            type="date"
            class="w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-1.5 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
            @change="applyFilters"
          >
        </div>

        <!-- Due to -->
        <div class="space-y-1">
          <label
            for="task-due-to"
            class="block text-[11px] font-semibold uppercase tracking-wide text-white/50"
          >
            Due to
          </label>
          <input
            id="task-due-to"
            v-model="dueToFilter"
            type="date"
            class="w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-1.5 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
            @change="applyFilters"
          >
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10"
          @click="resetFilters"
        >
          Reset filters
        </button>
      </div>
    </div>

    <!-- Tasks table -->
    <div class="rounded-2xl border border-white/10 bg-slate-950/80 overflow-hidden">
      <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-2 border-b border-white/10 bg-slate-950/90 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white/50">
        <div>Task</div>
        <div>Project</div>
        <div>Status</div>
        <div>Priority</div>
        <div>Due</div>
      </div>

      <div v-if="!props.tasks.data.length" class="px-4 py-6">
        <EmptyState
          :title="hasFilters ? 'No tasks match your filters' : 'All caught up!'"
          :description="hasFilters ? 'Try adjusting your filters to see more tasks.' : 'No active tasks right now.'"
          icon="✓"
        >
          <template v-if="hasFilters" #action>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-medium text-white hover:bg-white/10 transition"
              @click="resetFilters"
            >
              Reset filters
            </button>
          </template>
          <template v-else #action>
            <Link
              :href="r('projects.index', { organization: org })"
              class="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition"
            >
              View projects
            </Link>
          </template>
        </EmptyState>
      </div>

      <div
        v-for="task in props.tasks.data"
        :key="task.id"
        class="grid grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] items-center gap-2 border-t border-white/5 px-4 py-3 text-xs"
      >
        <div class="space-y-0.5">
          <button
            type="button"
            class="block w-full truncate text-left text-sm font-medium text-white hover:underline underline-offset-4"
            @click="openTask(task.id)"
          >
            {{ task.title }}
          </button>
        </div>

        <div class="truncate text-xs text-white/70">
          {{ task.project?.title ?? '—' }}
        </div>

        <div>
          <span :class="statusClass(task.status)">
            {{ task.status || '—' }}
          </span>
        </div>

        <div :class="priorityClass(task.priority)">
          {{ task.priority || '—' }}
        </div>

        <div class="text-xs text-white/70">
          {{ formatDate(task.due_date) }}
        </div>
      </div>

      <div
        v-if="props.tasks.links.length > 1"
        class="flex items-center justify-between border-t border-white/10 bg-slate-950 px-4 py-3"
      >
        <div class="text-xs text-white/50">
          Showing {{ props.tasks.data.length }} tasks
        </div>

        <div class="flex items-center gap-1">
          <template v-for="link in props.tasks.links" :key="link.label">
            <button
              v-if="link.url"
              type="button"
              class="rounded-md px-3 py-1.5 text-xs"
              :class="[
                link.active
                  ? 'bg-indigo-500 text-white shadow-[0_0_20px_rgba(129,140,248,0.6)]'
                  : 'bg-transparent text-white/70 hover:bg-white/10',
              ]"
              v-html="link.label"
              @click.prevent="router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
            />
            <span
              v-else
              class="rounded-md px-3 py-1.5 text-xs text-white/40"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>

    <TaskDrawer
      :show="drawerOpen"
      :task-id="drawerTaskId"
      :organization-slug="org"
      @close="closeDrawer"
    />
    </div>
  </PageShell>
</template>
