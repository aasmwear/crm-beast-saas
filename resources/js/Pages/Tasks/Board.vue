<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TaskDrawer from '@/Components/tasks/TaskDrawer.vue'

defineOptions({ layout: AuthenticatedLayout })

interface ProjectSummary {
  id: number
  title: string
}

interface BoardTask {
  id: number
  title: string
  status: string | null
  priority: string | null
  due_date: string | null
  project: ProjectSummary
  can_update: boolean
}

const props = defineProps<{
  organization: { id: number; name: string; slug: string }
  tasks: BoardTask[]
}>()

const page = usePage()

const orgSlug = computed(() => {
  const p = page.props as any
  return props.organization?.slug ?? p?.tenant?.slug ?? p?.organization?.slug ?? 'acme'
})

const columns = [
  { key: 'todo', label: 'Todo' },
  { key: 'in-progress', label: 'In Progress' },
  { key: 'submitted', label: 'Submitted' },
  { key: 'approved', label: 'Approved' },
  { key: 'changes-requested', label: 'Changes Requested' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'done', label: 'Done' },
] as const

type ColumnKey = (typeof columns)[number]['key']

function normalizeStatus(status: string | null | undefined): ColumnKey {
  if (!status) return 'todo'

  const s = status.trim().toLowerCase()

  if (['todo', 'backlog', 'open', 'new'].includes(s)) return 'todo'
  if (['in progress', 'in_progress', 'doing', 'active'].includes(s)) return 'in-progress'
  if (['submitted', 'pending review', 'pending_review'].includes(s)) return 'submitted'
  if (['approved'].includes(s)) return 'approved'
  if (['changes requested', 'changes_requested', 'needs changes'].includes(s)) return 'changes-requested'
  if (['rejected', 'blocked'].includes(s)) return 'rejected'
  if (['done', 'completed', 'closed', 'finished'].includes(s)) return 'done'

  return 'todo'
}

function tasksInColumn(key: ColumnKey): BoardTask[] {
  return props.tasks.filter((t) => normalizeStatus(t.status) === key)
}

function priorityClass(priority: string | null): string {
  const value = (priority ?? '').toLowerCase().trim()
  if (!value) return 'text-white/60'
  if (['low'].includes(value)) return 'text-emerald-300'
  if (['medium', 'normal'].includes(value)) return 'text-amber-300'
  if (['high', 'urgent'].includes(value)) return 'text-red-300'
  return 'text-white/60'
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' })
}

const isUpdating = ref<number | null>(null)

const drawerOpen = ref(false)
const drawerTaskId = ref<number | null>(null)

function openTask(taskId: number) {
  drawerTaskId.value = taskId
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

function updateStatus(task: BoardTask, columnKey: ColumnKey) {
  if (!task.can_update) return

  const col = columns.find((c) => c.key === columnKey)
  if (!col) return

  isUpdating.value = task.id

  router.put(
    route('tasks.update', {
      organization: orgSlug.value,
      task: task.id,
    }),
    { status: col.label },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isUpdating.value = null
      },
    },
  )
}

function onColumnChange(task: BoardTask, event: Event) {
  const target = event.target as HTMLSelectElement
  const value = target.value as ColumnKey
  updateStatus(task, value)
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
            Task Board
          </h1>
          <p class="mt-1 text-sm text-white/60">
            Kanban view of tasks across the organization.
          </p>
        </div>

        <div class="inline-flex items-center gap-1 rounded-full bg-white/5 p-1 text-xs">
          <Link
            :href="route('tasks.index', { organization: orgSlug })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            List
          </Link>
          <span
            class="rounded-full bg-[var(--primary)] px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow-[0_0_24px_rgba(139,124,255,0.65)]"
          >
            Board
          </span>
        </div>
      </div>
    </div>

    <!-- Columns -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
      <div
        v-for="column in columns"
        :key="column.key"
        class="glass flex min-h-[260px] flex-col rounded-2xl border border-white/5 bg-slate-950/40 p-3"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-100">
            {{ column.label }}
          </div>
          <div class="rounded-full bg-white/5 px-2 py-0.5 text-[10px] text-white/60">
            {{ tasksInColumn(column.key).length }}
          </div>
        </div>

        <div class="flex-1 space-y-2 overflow-y-auto pr-1">
          <div
            v-for="task in tasksInColumn(column.key)"
            :key="task.id"
            class="group cursor-pointer rounded-xl border border-white/5 bg-white/5 p-3 text-sm transition hover:border-[var(--primary)] hover:bg-white/10"
            @click="openTask(task.id)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="line-clamp-2 font-medium text-white">
                  {{ task.title }}
                </div>
                <div class="mt-1 text-[11px] text-white/50">
                  Project:
                  <Link
                    :href="route('projects.show', { organization: orgSlug, project: task.project.id })"
                    class="text-white/70 hover:text-white underline-offset-4 hover:underline"
                    @click.stop
                  >
                    {{ task.project.title }}
                  </Link>
                </div>
              </div>

              <div class="shrink-0 text-[10px] text-white/40">
                #{{ task.id }}
              </div>
            </div>

            <div class="mt-3 flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <div class="text-[10px] text-white/50">
                  Due
                  <span class="text-white/70">{{ formatDate(task.due_date) }}</span>
                </div>
                <div
                  v-if="task.priority"
                  class="text-[10px] uppercase tracking-wide"
                  :class="priorityClass(task.priority)"
                >
                  {{ task.priority }}
                </div>
              </div>

              <select
                class="rounded-full border border-white/15 bg-slate-950/60 px-2 py-1 text-[10px] text-white/70 focus:border-[var(--primary)] focus:outline-none disabled:opacity-50"
                :value="column.key"
                :disabled="isUpdating === task.id || !task.can_update"
                @change="onColumnChange(task, $event)"
                @click.stop
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

            <div
              v-if="!task.can_update"
              class="mt-2 text-[10px] text-white/35"
            >
              Read-only
            </div>
          </div>

          <div
            v-if="!tasksInColumn(column.key).length"
            class="mt-4 rounded-xl border border-dashed border-white/10 bg-slate-950/40 p-4 text-center text-xs text-white/40"
          >
            No tasks here.
          </div>
        </div>
      </div>
    </div>

    <TaskDrawer
      :show="drawerOpen"
      :task-id="drawerTaskId"
      :organization-slug="orgSlug"
      @close="closeDrawer"
    />
  </div>
</template>
