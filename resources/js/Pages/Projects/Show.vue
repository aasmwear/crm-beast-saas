<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

type ProjectUser = {
  id: number
  name: string
}

type ProjectClient = {
  id: number
  company_name: string
}

type ProjectDepartment = {
  id: number
  name: string
}

type TaskBrief = {
  id: number
  title: string
  status: string | null
  priority: string | null
  due_date: string | null
}

type ProjectPayload = {
  id: number
  title: string
  project_code: string | null
  description: string | null
  status: string | null
  start_date: string | null
  end_date: string | null
  budget: number | string | null
  price: number | string | null
  billable: boolean
  google_business_profile_status: string | null
  google_business_profile_access_status: string | null
  client_activation_status: string | null
  notes_by_cst: string | null
  notes_by_sales: string | null
  notes_by_tech: string | null
  client?: ProjectClient | null
  manager?: ProjectUser | null
  department?: ProjectDepartment | null
  tasks?: TaskBrief[] | null
}

const props = defineProps<{
  project: ProjectPayload
  organizationSlug: string
}>()

// Safe Ziggy route helper
const routeGlobal =
  (window as any).route as
    | ((name: string, params?: any, absolute?: boolean, config?: any) => string)
    | undefined

const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

const destroyProject = () => {
  if (
    !confirm(
      'Are you sure you want to permanently delete this project? This cannot be undone.',
    )
  ) {
    return
  }

  router.delete(
    r('projects.destroy', {
      organization: props.organizationSlug,
      project: props.project.id,
    }),
  )
}

const getStatusClass = (status: string | null) => {
  const s = (status || '').toLowerCase()
  const base =
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '

  if (s === 'planned') return base + 'bg-sky-900/60 text-sky-200'
  if (s === 'in progress' || s === 'in_progress' || s === 'active') {
    return base + 'bg-emerald-900/60 text-emerald-200'
  }
  if (s === 'blocked') return base + 'bg-amber-900/60 text-amber-200'
  if (s === 'completed' || s === 'done') {
    return base + 'bg-indigo-900/60 text-indigo-200'
  }

  return base + 'bg-slate-800/70 text-slate-200'
}

const getTaskStatusDotClass = (status: string | null) => {
  const s = (status || '').toLowerCase()

  if (s === 'completed' || s === 'done') return 'bg-emerald-400'
  if (s === 'in progress' || s === 'in_progress' || s === 'doing') {
    return 'bg-amber-400'
  }
  if (s === 'blocked' || s === 'cancelled') return 'bg-red-400'

  return 'bg-slate-500'
}

const formatMoney = (value: number | string | null) => {
  if (value === null || value === '') return '—'
  const num = typeof value === 'string' ? Number.parseFloat(value) : value
  if (!Number.isFinite(num)) return String(value)

  return num.toLocaleString(undefined, {
    maximumFractionDigits: 0,
  })
}

const hasTasks = () => !!props.project.tasks && props.project.tasks.length > 0

// Quick-add task form
const taskForm = useForm({
  project_id: props.project.id,
  title: '',
  due_date: '',
})

const submitTask = () => {
  // Keep project_id in sync in case of future navigation
  taskForm.project_id = props.project.id

  if (!taskForm.title || !taskForm.title.trim()) {
    return
  }

  taskForm.post(
    r('tasks.store', {
      organization: props.organizationSlug,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        taskForm.reset('title', 'due_date')
      },
    },
  )
}
</script>

<template>
  <div class="max-w-6xl mx-auto py-6">
    <div class="card-neo p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
          <div class="flex items-center gap-3">
            <h1 class="text-3xl font-semibold text-white">
              {{ props.project.title }}
            </h1>

            <span
              v-if="props.project.status"
              :class="getStatusClass(props.project.status)"
            >
              {{ props.project.status }}
            </span>

            <span
              v-if="props.project.project_code"
              class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-0.5 text-xs font-medium text-white/80"
            >
              #{{ props.project.project_code }}
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs text-white/70">
            <span v-if="props.project.client">
              Client:
              <span class="font-medium text-white">
                {{ props.project.client.company_name }}
              </span>
            </span>

            <span v-if="props.project.manager">
              <span v-if="props.project.client" class="text-white/30">•</span>
              PM:
              <span class="text-white">
                {{ props.project.manager.name }}
              </span>
            </span>

            <span v-if="props.project.department">
              <span
                v-if="props.project.client || props.project.manager"
                class="text-white/30"
              >
                •
              </span>
              Dept:
              <span class="text-white">
                {{ props.project.department.name }}
              </span>
            </span>

            <span
              v-if="props.project.start_date || props.project.end_date"
              class="flex flex-wrap items-center gap-1"
            >
              <span
                v-if="
                  props.project.client ||
                  props.project.manager ||
                  props.project.department
                "
                class="text-white/30"
              >
                •
              </span>
              <span class="text-white/60">Timeline:</span>
              <span v-if="props.project.start_date">
                From {{ props.project.start_date }}
              </span>
              <span
                v-if="props.project.start_date && props.project.end_date"
                class="text-white/40"
              >
                &mdash;
              </span>
              <span v-if="props.project.end_date">
                To {{ props.project.end_date }}
              </span>
            </span>
          </div>
        </div>

        <div class="flex flex-col items-end gap-3">
          <div class="flex items-center gap-2">
            <Link
              :href="r('projects.index', { organization: props.organizationSlug })"
              class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 hover:bg-white/10"
            >
              Back to projects
            </Link>

            <Link
              :href="
                r('projects.edit', {
                  organization: props.organizationSlug,
                  project: props.project.id,
                })
              "
              class="inline-flex items-center rounded-full bg-indigo-500 px-3 py-1.5 text-xs font-semibold text-white shadow-[0_10px_30px_rgba(129,140,248,0.5)] hover:bg-indigo-400"
            >
              Edit project
            </Link>

            <button
              type="button"
              class="inline-flex items-center rounded-full border border-red-500/60 bg-red-500/10 px-3 py-1.5 text-xs font-semibold text-red-200 hover:bg-red-500/20"
              @click="destroyProject"
            >
              Delete
            </button>
          </div>

          <p class="text-[11px] text-white/40">
            Project ID: {{ props.project.id }}
          </p>
        </div>
      </div>

      <div
        class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
      >
        <!-- Main column -->
        <div class="space-y-6">
          <!-- Summary -->
          <section>
            <h2 class="text-lg font-medium text-white mb-3">
              Summary
            </h2>
            <dl class="grid grid-cols-1 gap-3 text-xs text-white/70 md:grid-cols-2">
              <div class="flex">
                <dt class="w-32 text-white/50">
                  Budget
                </dt>
                <dd class="flex-1 text-white">
                  {{ formatMoney(props.project.budget) }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-32 text-white/50">
                  Price
                </dt>
                <dd class="flex-1 text-white">
                  {{ formatMoney(props.project.price) }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-32 text-white/50">
                  Billable
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.billable ? 'Yes' : 'No' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-32 text-white/50">
                  GBP status
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.google_business_profile_status || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-32 text-white/50">
                  GBP access
                </dt>
                <dd class="flex-1 text-white">
                  {{
                    props.project.google_business_profile_access_status || '—'
                  }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-32 text-white/50">
                  Activation
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.client_activation_status || '—' }}
                </dd>
              </div>
            </dl>
          </section>

          <!-- Description -->
          <section>
            <h2 class="text-lg font-medium text-white mb-3">
              Description
            </h2>
            <p
              class="text-sm text-white/70 whitespace-pre-wrap min-h-[3rem]"
            >
              {{ props.project.description || 'No description added yet.' }}
            </p>
          </section>

          <!-- Tasks -->
          <section>
            <h2 class="text-lg font-medium text-white mb-3">
              Tasks
            </h2>

            <div v-if="hasTasks()" class="space-y-2">
              <div
                v-for="task in props.project.tasks"
                :key="task.id"
                class="flex items-center justify-between rounded-xl border border-white/10 bg-slate-900/70 px-3 py-2 text-xs"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span
                    class="inline-flex h-1.5 w-1.5 rounded-full"
                    :class="getTaskStatusDotClass(task.status)"
                  />
                  <span class="truncate text-white/80">
                    {{ task.title }}
                  </span>
                  <span
                    v-if="task.status"
                    class="text-[11px] text-white/40"
                  >
                    • {{ task.status }}
                  </span>
                  <span
                    v-if="task.priority"
                    class="text-[11px] text-white/40"
                  >
                    • {{ task.priority }}
                  </span>
                </div>

                <div
                  v-if="task.due_date"
                  class="whitespace-nowrap text-[11px] text-white/50"
                >
                  Due {{ task.due_date }}
                </div>
              </div>
            </div>

            <p v-else class="text-sm text-white/50">
              No tasks for this project yet.
            </p>

            <!-- Quick add task -->
            <div
              class="mt-4 rounded-xl border border-dashed border-white/15 bg-slate-900/60 p-3"
            >
              <h3
                class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-white/60"
              >
                Quick add task
              </h3>

              <form
                class="flex flex-col gap-3 sm:flex-row sm:items-end"
                @submit.prevent="submitTask"
              >
                <div class="flex-1 space-y-1">
                  <label
                    for="quick-task-title"
                    class="block text-[11px] uppercase tracking-wide text-white/50"
                  >
                    Title
                  </label>
                  <input
                    id="quick-task-title"
                    v-model="taskForm.title"
                    type="text"
                    required
                    class="w-full rounded-lg border border-white/10 bg-slate-900/80 px-3 py-1.5 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                    placeholder="Write a short task name"
                  >
                  <p
                    v-if="taskForm.errors.title"
                    class="mt-1 text-[11px] text-red-400"
                  >
                    {{ taskForm.errors.title }}
                  </p>
                </div>

                <div class="space-y-1">
                  <label
                    for="quick-task-due"
                    class="block text-[11px] uppercase tracking-wide text-white/50"
                  >
                    Due date
                  </label>
                  <input
                    id="quick-task-due"
                    v-model="taskForm.due_date"
                    type="date"
                    class="rounded-lg border border-white/10 bg-slate-900/80 px-3 py-1.5 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                  >
                </div>

                <div class="pt-1 sm:pt-0">
                  <button
                    type="submit"
                    class="inline-flex items-center rounded-full bg-indigo-500 px-4 py-1.5 text-xs font-semibold text-white shadow-[0_8px_25px_rgba(129,140,248,.5)] hover:bg-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:opacity-60"
                    :disabled="taskForm.processing"
                  >
                    <span v-if="taskForm.processing">
                      Adding...
                    </span>
                    <span v-else>
                      Add task
                    </span>
                  </button>
                </div>
              </form>
            </div>
          </section>

          <!-- Internal notes -->
          <section>
            <h2 class="text-lg font-medium text-white mb-3">
              Internal notes
            </h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div
                class="rounded-xl border border-white/10 bg-slate-900/60 p-3"
              >
                <h3
                  class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-white/60"
                >
                  Sales
                </h3>
                <p
                  class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]"
                >
                  {{ props.project.notes_by_sales || 'No notes yet' }}
                </p>
              </div>

              <div
                class="rounded-xl border border-white/10 bg-slate-900/60 p-3"
              >
                <h3
                  class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-white/60"
                >
                  CST
                </h3>
                <p
                  class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]"
                >
                  {{ props.project.notes_by_cst || 'No notes yet' }}
                </p>
              </div>

              <div
                class="rounded-xl border border-white/10 bg-slate-900/60 p-3"
              >
                <h3
                  class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-white/60"
                >
                  Tech
                </h3>
                <p
                  class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]"
                >
                  {{ props.project.notes_by_tech || 'No notes yet' }}
                </p>
              </div>
            </div>
          </section>
        </div>

        <!-- Side column -->
        <aside class="space-y-4">
          <section
            class="rounded-2xl border border-white/10 bg-slate-950/70 p-4 space-y-3"
          >
            <h2 class="text-sm font-semibold text-white">
              Project meta
            </h2>
            <dl class="space-y-1 text-xs text-white/70">
              <div class="flex">
                <dt class="w-24 text-white/50">
                  Client
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.client?.company_name || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-24 text-white/50">
                  PM
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.manager?.name || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-24 text-white/50">
                  Department
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.department?.name || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-24 text-white/50">
                  Status
                </dt>
                <dd class="flex-1 text-white">
                  {{ props.project.status || '—' }}
                </dd>
              </div>
            </dl>
          </section>

          <section
            class="rounded-2xl border border-white/10 bg-slate-950/70 p-4 space-y-3"
          >
            <h2 class="text-sm font-semibold text-white">
              Project messages
            </h2>
            <p class="text-xs text-white/70">
              Keep project discussions in one place. Use messages for
              implementation notes, questions, and decisions.
            </p>
            <Link
              :href="
                r('projects.messages.index', {
                  organization: props.organizationSlug,
                  project: props.project.id,
                })
              "
              class="w-full text-center inline-flex items-center justify-center rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20"
            >
              Open messages
            </Link>
          </section>
        </aside>
      </div>
    </div>
  </div>
</template>
