<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import ActivityFeed from '@/Components/ActivityFeed.vue'
import CommentStream from '@/Components/CommentStream.vue'
import PageShell from '@/Components/ui/PageShell.vue'
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

type ProjectFileBrief = {
  id: number
  filename: string
  path: string
  mime_type: string | null
  size: number
  is_visible_to_client: boolean
  created_at: string | null
  uploader: { id: number; name: string } | null
}

type CommentBrief = {
  id: number
  body: string
  created_at: string | null
  user: { id: number; name: string } | null
}

type ActivityBrief = {
  id: number
  description: string
  properties: Record<string, unknown> | null
  created_at: string | null
  user: { id: number; name: string } | null
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
  currency: string | null
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
  users: ProjectUser[]
  files?: ProjectFileBrief[]
  comments?: CommentBrief[]
  activities?: ActivityBrief[]
  canEdit?: boolean
  canDelete?: boolean
  canManage?: boolean
  canCreateTask?: boolean
}>()

const page = usePage()
const currentUserId = (page.props.auth as { user?: { id: number } })?.user?.id

const canDeleteComment = (comment: CommentBrief): boolean => {
  const user = (page.props.auth as { user?: { id: number; is_super_admin?: boolean } })?.user
  const isSuperAdmin = user?.is_super_admin ?? false
  const isAuthor = currentUserId != null && comment.user?.id === currentUserId
  return isAuthor || isSuperAdmin
}

const activeTab = ref<'overview' | 'tasks' | 'files' | 'notes'>('overview')

const headerSubtitle = computed(() => {
  const parts: string[] = []
  if (props.project.status) parts.push(props.project.status)
  if (props.project.project_code) parts.push(`#${props.project.project_code}`)
  if (props.project.client) parts.push(`Client: ${props.project.client.company_name}`)
  if (props.project.manager) parts.push(`PM: ${props.project.manager.name}`)
  if (props.project.department) parts.push(`Dept: ${props.project.department.name}`)
  if (props.project.start_date || props.project.end_date) {
    const range = [props.project.start_date, props.project.end_date].filter(Boolean).join(' — ')
    parts.push(`Timeline: ${range}`)
  }
  return parts.join(' • ')
})

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

function formatMoney(
  value: number | string | null | undefined,
  currency: string = 'USD',
): string {
  if (value === null || value === undefined || value === '') return '—'
  const num = typeof value === 'string' ? Number.parseFloat(value) : value
  if (!Number.isFinite(num)) return '—'

  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
}

const hasTasks = () => !!props.project.tasks && props.project.tasks.length > 0

// Quick-add task form
const taskForm = useForm({
  project_id: props.project.id,
  title: '',
  due_date: '',
  assignees: [] as number[],
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
        taskForm.reset('title', 'due_date', 'assignees')
      },
    },
  )
}

// --- Project Files ---
const files = () => props.files ?? []

const fileInputRef = ref<HTMLInputElement | null>(null)
const dropzoneActive = ref(false)

function getFileIcon(mime: string | null): string {
  if (!mime) return '📄'
  const m = (mime || '').toLowerCase()
  if (m.startsWith('image/')) return '🖼️'
  if (m.includes('pdf')) return '📕'
  if (m.includes('sheet') || m.includes('excel')) return '📊'
  if (m.includes('word') || m.includes('document')) return '📘'
  if (m.includes('video')) return '🎬'
  if (m.includes('audio')) return '🎵'
  return '📄'
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function openFilePicker() {
  fileInputRef.value?.click()
}

function onFileSelected(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) uploadFile(file)
  input.value = ''
}

function onDrop(e: DragEvent) {
  dropzoneActive.value = false
  e.preventDefault()
  const file = e.dataTransfer?.files?.[0]
  if (file) uploadFile(file)
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  dropzoneActive.value = true
}

function onDragLeave() {
  dropzoneActive.value = false
}

function uploadFile(file: File) {
  if (file.size > 10 * 1024 * 1024) {
    alert('File must be 10MB or smaller.')
    return
  }
  const formData = new FormData()
  formData.append('file', file)
  router.post(
    r('projects.files.store', {
      organization: props.organizationSlug,
      project: props.project.id,
    }),
    formData,
    {
      forceFormData: true,
      preserveScroll: true,
    },
  )
}

function downloadFile(f: ProjectFileBrief) {
  window.location.href = r('projects.files.download', {
    organization: props.organizationSlug,
    project: props.project.id,
    projectFile: f.id,
  })
}

function toggleVisibility(f: ProjectFileBrief) {
  router.patch(
    r('projects.files.toggleVisibility', {
      organization: props.organizationSlug,
      project: props.project.id,
      projectFile: f.id,
    }),
    {},
    { preserveScroll: true },
  )
}

function deleteFile(f: ProjectFileBrief) {
  if (!confirm(`Delete "${f.filename}"? This cannot be undone.`)) return
  router.delete(
    r('projects.files.destroy', {
      organization: props.organizationSlug,
      project: props.project.id,
      projectFile: f.id,
    }),
    { preserveScroll: true },
  )
}
</script>

<template>
  <PageShell
    v-model="activeTab"
    :sticky="true"
    :tabs="[
      { key: 'overview', label: 'Overview' },
      { key: 'tasks', label: 'Tasks' },
      { key: 'files', label: 'Files' },
      { key: 'notes', label: 'Internal notes' },
    ]"
    :local="true"
    :header="{
      breadcrumb: 'Projects',
      title: props.project.title,
      subtitle: headerSubtitle || undefined,
    }"
  >
    <template #header-actions>
      <div class="flex flex-col items-end gap-3">
        <div class="flex items-center gap-2">
          <Link
            :href="r('projects.index', { organization: props.organizationSlug })"
            class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 hover:bg-white/10"
          >
            Back to projects
          </Link>

          <Link
            v-if="canEdit !== false"
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
            v-if="canDelete !== false"
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
    </template>

    <div class="max-w-6xl mx-auto">
      <div class="card-neo p-6 space-y-6">
      <div
        class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
      >
        <!-- Main column -->
        <div class="space-y-6">
          <!-- Overview tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
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
                  {{ formatMoney(props.project?.budget, props.project?.currency ?? 'USD') }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-32 text-white/50">
                  Price
                </dt>
                <dd class="flex-1 text-white">
                  {{ formatMoney(props.project?.price, props.project?.currency ?? 'USD') }}
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
          </div>

          <!-- Tasks tab -->
          <section v-show="activeTab === 'tasks'">
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
              v-if="canCreateTask !== false"
              class="mt-4 rounded-xl border border-dashed border-white/15 bg-slate-900/60 p-3"
            >
              <h3
                class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-white/60"
              >
                Quick add task
              </h3>

              <form
                class="flex flex-col gap-3"
                @submit.prevent="submitTask"
              >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
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
                </div>

                <div class="space-y-1">
                  <label
                    for="quick-task-assignees"
                    class="block text-[11px] uppercase tracking-wide text-white/50"
                  >
                    Assignees
                  </label>
                  <select
                    id="quick-task-assignees"
                    v-model="taskForm.assignees"
                    multiple
                    size="4"
                    class="w-full rounded-lg border border-white/10 bg-slate-900/80 px-3 py-1.5 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                  >
                    <option
                      v-for="user in props.users"
                      :key="user.id"
                      :value="user.id"
                    >
                      {{ user.name }}
                    </option>
                  </select>
                  <p class="text-[11px] text-white/40">
                    Hold Ctrl/Cmd to select multiple
                  </p>
                  <p
                    v-if="taskForm.errors.assignees"
                    class="mt-1 text-[11px] text-red-400"
                  >
                    {{ taskForm.errors.assignees }}
                  </p>
                </div>

                <div class="pt-1">
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

          <!-- Files tab -->
          <section v-show="activeTab === 'files'" class="space-y-4">
            <h2 class="text-lg font-medium text-white">
              Project files
            </h2>

            <!-- Drop zone -->
            <div
              v-if="canEdit !== false"
              class="rounded-xl border-2 border-dashed transition-colors"
              :class="
                dropzoneActive
                  ? 'border-indigo-400 bg-indigo-500/10'
                  : 'border-white/15 bg-slate-900/60 hover:border-white/25'
              "
              @drop.prevent="onDrop"
              @dragover.prevent="onDragOver"
              @dragleave="onDragLeave"
              @click="openFilePicker"
            >
              <input
                ref="fileInputRef"
                type="file"
                class="hidden"
                accept="*/*"
                @change="onFileSelected"
              >
              <div class="flex flex-col items-center justify-center py-8 px-4 text-center cursor-pointer">
                <span class="text-3xl mb-2">📁</span>
                <p class="text-sm text-white/80">
                  Drop files here to upload (max 10MB)
                </p>
                <p class="text-xs text-white/50 mt-1">
                  or click to select a file
                </p>
              </div>
            </div>

            <!-- File grid -->
            <div
              v-if="files().length > 0"
              class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
              <div
                v-for="f in files()"
                :key="f.id"
                class="flex items-center gap-3 rounded-xl border border-white/10 bg-slate-900/70 p-3"
              >
                <span class="text-2xl shrink-0" :title="f.mime_type || 'file'">
                  {{ getFileIcon(f.mime_type) }}
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-medium text-white" :title="f.filename">
                    {{ f.filename }}
                  </p>
                  <p class="text-[11px] text-white/50">
                    {{ f.uploader?.name ?? 'Unknown' }} · {{ formatFileSize(f.size) }}
                  </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                  <button
                    type="button"
                    class="rounded p-1.5 text-white/60 hover:bg-white/10 hover:text-white"
                    title="Download"
                    @click.stop="downloadFile(f)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                  <button
                    v-if="canEdit !== false"
                    type="button"
                    :class="[
                      'rounded p-1.5',
                      f.is_visible_to_client
                        ? 'text-emerald-400 hover:bg-emerald-500/20'
                        : 'text-white/50 hover:bg-white/10 hover:text-white',
                    ]"
                    :title="f.is_visible_to_client ? 'Visible to client (click to hide)' : 'Hidden from client (click to show)'"
                    @click.stop="toggleVisibility(f)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button
                    v-if="canEdit !== false"
                    type="button"
                    class="rounded p-1.5 text-white/50 hover:bg-red-500/20 hover:text-red-400"
                    title="Delete"
                    @click.stop="deleteFile(f)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-white/50">
              No files uploaded yet.
            </p>
          </section>

          <!-- Internal notes tab -->
          <section v-show="activeTab === 'notes'">
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

          <section class="rounded-2xl border border-white/10 bg-slate-950/70 p-4">
            <ActivityFeed :activities="props.activities ?? []" />
          </section>

          <section class="rounded-2xl border border-white/10 bg-slate-950/70 p-4">
            <CommentStream
              :comments="props.comments ?? []"
              :post-url="r('projects.comments.store', { organization: props.organizationSlug, project: props.project.id })"
              :organization-slug="props.organizationSlug"
              :current-user-id="currentUserId"
              :can-delete="canDeleteComment"
            />
          </section>
        </aside>
      </div>
    </div>
    </div>
  </PageShell>
</template>
