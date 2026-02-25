<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

type UserBrief = {
  id: number
  name: string
}

type ProjectBrief = {
  id: number
  title: string
}

type TaskComment = {
  body?: string
  message?: string
  at?: string
  by?: { id?: number; name?: string } | string
}

type TaskPayload = {
  id: number
  title: string
  description: string | null
  status: string | null
  priority: string | null
  due_date: string | null
  estimated_hours: number | null
  logged_hours: number | null
  project?: ProjectBrief | null
  submission?: any[] | Record<string, any> | null
  submission_note?: string | null
  submission_files?: any[] | null
  review_status?: string | null
  comments?: any[] | null
  reviewed_by_id?: number | null
  reviewer?: UserBrief | null
  can_update?: boolean
  can_submit?: boolean
  can_review?: boolean
}

const props = withDefaults(
  defineProps<{
    show: boolean
    taskId: number | null
    organizationSlug: string
  }>(),
  {
    show: false,
    taskId: null,
  },
)

const emit = defineEmits<{ (e: 'close'): void }>()

const page = usePage<any>()
const authUser = computed<UserBrief | null>(() => (page.props as any)?.auth?.user ?? null)

const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

const isLoading = ref(false)
const task = ref<TaskPayload | null>(null)
const loadError = ref<string | null>(null)

// Editable fields
const editTitle = ref('')
const editDescription = ref('')
const editStatus = ref<string>('')
const editPriority = ref<string>('')
const editDueDate = ref<string>('')
const editEstimatedHours = ref<string>('')
const editLoggedHours = ref<string>('')

// Submit fields
const submissionNote = ref('')
const submissionLinksText = ref('')
const submissionFilesText = ref('')

// Review fields
const reviewStatus = ref<string>('Approved')
const reviewComment = ref<string>('')

const statusOptions = [
  'Todo',
  'In Progress',
  'Submitted',
  'Approved',
  'Changes Requested',
  'Rejected',
  'Done',
]

const priorityOptions = ['', 'Low', 'Medium', 'High', 'Urgent']

const canUpdate = computed(() => !!task.value?.can_update)
const canSubmit = computed(() => !!task.value?.can_submit)
const canReview = computed(() => !!task.value?.can_review)

const normalizedComments = computed<TaskComment[]>(() => {
  const raw = task.value?.comments
  if (!Array.isArray(raw)) return []

  return raw
    .map((c: any) => {
      if (typeof c === 'string') return { body: c }
      if (c && typeof c === 'object') return c as TaskComment
      return {}
    })
    .filter((c) => (c.body ?? c.message ?? '').toString().trim().length > 0)
})

const normalizedSubmissionLinks = computed<string[]>(() => {
  const s = task.value?.submission
  if (!s) return []

  if (Array.isArray(s)) {
    return s
      .map((x) => (typeof x === 'string' ? x : JSON.stringify(x)))
      .filter((x) => x.trim().length > 0)
  }

  // If submission is an object, show as a single JSON blob
  return [JSON.stringify(s)]
})

function close() {
  emit('close')
}

function onKeydown(e: KeyboardEvent) {
  if (!props.show) return
  if (e.key === 'Escape') {
    e.preventDefault()
    close()
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

async function fetchTask() {
  if (!props.taskId) return

  isLoading.value = true
  loadError.value = null

  try {
    const resp = await axios.get(
      r('tasks.show', {
        organization: props.organizationSlug,
        task: props.taskId,
      }),
      {
        headers: { Accept: 'application/json' },
        validateStatus: () => true, // Don't throw on 4xx/5xx so we can handle them
      },
    )

    if (resp.status === 403) {
      loadError.value = 'You do not have permission to view this task.'
      task.value = null
      return
    }
    if (resp.status === 404) {
      loadError.value = 'Task not found.'
      task.value = null
      return
    }
    if (resp.status >= 500) {
      loadError.value = 'Server error. Please try again.'
      task.value = null
      return
    }
    if (resp.status !== 200 || typeof resp.data !== 'object') {
      loadError.value = 'Failed to load task.'
      task.value = null
      return
    }

    task.value = resp.data as TaskPayload

    // hydrate editable fields
    editTitle.value = String(task.value.title ?? '')
    editDescription.value = String(task.value.description ?? '')
    editStatus.value = String(task.value.status ?? '')
    editPriority.value = String(task.value.priority ?? '')
    editDueDate.value = task.value.due_date ? String(task.value.due_date).slice(0, 10) : ''
    editEstimatedHours.value =
      task.value.estimated_hours === null || task.value.estimated_hours === undefined
        ? ''
        : String(task.value.estimated_hours)
    editLoggedHours.value =
      task.value.logged_hours === null || task.value.logged_hours === undefined
        ? ''
        : String(task.value.logged_hours)

    submissionNote.value = String(task.value.submission_note ?? '')
    submissionLinksText.value = normalizedSubmissionLinks.value.join('\n')
    submissionFilesText.value = Array.isArray(task.value.submission_files)
      ? task.value.submission_files
          .map((x: any) => (typeof x === 'string' ? x : JSON.stringify(x)))
          .join('\n')
      : ''

    // Review default
    reviewStatus.value = ['Approved', 'Changes Requested', 'Rejected'].includes(
      String(task.value.review_status ?? ''),
    )
      ? (String(task.value.review_status) as any)
      : 'Approved'

    reviewComment.value = ''
  } catch (e: any) {
    loadError.value = e?.response?.status === 403
      ? 'You do not have permission to view this task.'
      : e?.response?.status === 404
        ? 'Task not found.'
        : e?.response?.status >= 500
          ? 'Server error. Please try again.'
          : 'Failed to load task.'
    task.value = null
  } finally {
    isLoading.value = false
  }
}

function parseLines(text: string): string[] {
  return text
    .split('\n')
    .map((s) => s.trim())
    .filter((s) => s.length > 0)
}

function saveTask() {
  if (!task.value) return
  if (!canUpdate.value) return

  const payload: any = {
    title: editTitle.value.trim() || task.value.title,
    description: editDescription.value.trim() || null,
    status: editStatus.value || null,
    priority: editPriority.value || null,
    due_date: editDueDate.value || null,
    estimated_hours: editEstimatedHours.value !== '' ? Number(editEstimatedHours.value) : null,
    logged_hours: editLoggedHours.value !== '' ? Number(editLoggedHours.value) : null,
  }

  router.put(
    r('tasks.update', {
      organization: props.organizationSlug,
      task: task.value.id,
    }),
    payload,
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        fetchTask()
      },
    },
  )
}

function submitForReview() {
  if (!task.value) return
  if (!canSubmit.value) return

  const submissionLinks = parseLines(submissionLinksText.value)
  const submissionFiles = parseLines(submissionFilesText.value)

  router.post(
    r('tasks.submit', {
      organization: props.organizationSlug,
      task: task.value.id,
    }),
    {
      submission: submissionLinks,
      submission_note: submissionNote.value.trim() || null,
      submission_files: submissionFiles.length ? submissionFiles : null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        fetchTask()
      },
    },
  )
}

function reviewTask() {
  if (!task.value) return
  if (!canReview.value) return

  const existing = Array.isArray(task.value.comments) ? [...task.value.comments] : []

  const commentText = reviewComment.value.trim()
  if (commentText) {
    existing.push({
      body: commentText,
      at: new Date().toISOString(),
      by: authUser.value ? { id: authUser.value.id, name: authUser.value.name } : undefined,
    })
  }

  router.post(
    r('tasks.review', {
      organization: props.organizationSlug,
      task: task.value.id,
    }),
    {
      review_status: reviewStatus.value,
      comments: existing,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        reviewComment.value = ''
        fetchTask()
      },
    },
  )
}

watch(
  () => props.show,
  (v) => {
    if (v && props.taskId) {
      fetchTask()
    }

    if (!v) {
      task.value = null
      loadError.value = null
    }
  },
)

watch(
  () => props.taskId,
  (id) => {
    if (props.show && id) fetchTask()
  },
)
</script>

<template>
  <div v-if="props.show" class="fixed inset-0 z-[60]">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/70 backdrop-blur-[2px]"
      @click="close"
    />

    <!-- Panel -->
    <div
      class="absolute right-0 top-0 h-full w-full max-w-xl border-l border-white/10 bg-slate-950/90 shadow-[0_0_0_1px_rgba(255,255,255,0.04)_inset,0_30px_80px_rgba(0,0,0,0.6)]"
    >
      <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-start justify-between gap-3 border-b border-white/10 px-5 py-4">
          <div class="min-w-0">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-white/50">
              Task Details
            </div>
            <div class="mt-1 truncate text-lg font-semibold text-white">
              {{ task?.title ?? (isLoading ? 'Loading…' : '—') }}
            </div>
            <div v-if="task?.project" class="mt-1 text-xs text-white/60">
              Project:
              <Link
                class="text-white/80 hover:text-white underline-offset-4 hover:underline"
                :href="r('projects.show', { organization: props.organizationSlug, project: task.project.id })"
              >
                {{ task.project.title }}
              </Link>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10"
            @click="close"
          >
            Close
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto px-5 py-4">
          <div v-if="loadError" class="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200">
            {{ loadError }}
          </div>

          <div v-else-if="isLoading" class="space-y-3">
            <div class="h-4 w-1/2 rounded bg-white/10" />
            <div class="h-4 w-3/4 rounded bg-white/10" />
            <div class="h-24 rounded bg-white/10" />
          </div>

          <div v-else-if="task" class="space-y-6">
            <!-- Overview -->
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="mb-3 flex items-center justify-between">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-white/50">
                  Overview
                </div>
                <div class="text-[11px] text-white/40">
                  #{{ task.id }}
                </div>
              </div>

              <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Status</label>
                  <select
                    v-model="editStatus"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40 disabled:opacity-50"
                    :disabled="!canUpdate"
                  >
                    <option value="">—</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">
                      {{ s }}
                    </option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Priority</label>
                  <select
                    v-model="editPriority"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40 disabled:opacity-50"
                    :disabled="!canUpdate"
                  >
                    <option v-for="p in priorityOptions" :key="p" :value="p">
                      {{ p || '—' }}
                    </option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Due date</label>
                  <input
                    v-model="editDueDate"
                    type="date"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40 disabled:opacity-50"
                    :disabled="!canUpdate"
                  >
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Hours</label>
                  <div class="grid grid-cols-2 gap-2">
                    <input
                      v-model="editEstimatedHours"
                      inputmode="decimal"
                      placeholder="Est."
                      class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40 disabled:opacity-50"
                      :disabled="!canUpdate"
                    >
                    <input
                      v-model="editLoggedHours"
                      inputmode="decimal"
                      placeholder="Logged"
                      class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40 disabled:opacity-50"
                      :disabled="!canUpdate"
                    >
                  </div>
                </div>
              </div>

              <div class="mt-3 space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Description</label>
                <textarea
                  v-model="editDescription"
                  rows="4"
                  class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40 disabled:opacity-50"
                  :disabled="!canUpdate"
                  placeholder="Add a short description"
                />
              </div>

              <div class="mt-4 flex items-center justify-between">
                <div class="text-xs text-white/50">
                  Review status:
                  <span class="text-white/80">{{ task.review_status ?? '—' }}</span>
                </div>

                <button
                  type="button"
                  class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10 disabled:opacity-50"
                  :disabled="!canUpdate"
                  @click="saveTask"
                >
                  Save
                </button>
              </div>

              <div v-if="!canUpdate" class="mt-2 text-[11px] text-white/40">
                You can view this task, but you cannot edit it.
              </div>
            </div>

            <!-- Submission -->
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="mb-3 flex items-center justify-between">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-white/50">
                  Submission
                </div>
                <div class="text-xs text-white/50">
                  {{ task.review_status ?? '—' }}
                </div>
              </div>

              <div v-if="normalizedSubmissionLinks.length" class="mb-3 space-y-1">
                <div class="text-xs font-medium text-white/70">Submitted links</div>
                <ul class="space-y-1 text-xs text-white/70">
                  <li v-for="(lnk, idx) in normalizedSubmissionLinks" :key="idx" class="truncate">
                    <a :href="lnk" target="_blank" class="hover:underline underline-offset-4">
                      {{ lnk }}
                    </a>
                  </li>
                </ul>
              </div>

              <div v-if="task.submission_note" class="mb-3 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-white/50">Note</div>
                <div class="mt-1 text-xs text-white/70 whitespace-pre-line">{{ task.submission_note }}</div>
              </div>

              <div v-if="canSubmit" class="space-y-3">
                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Submission links (one per line)</label>
                  <textarea
                    v-model="submissionLinksText"
                    rows="3"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                    placeholder="https://drive.google.com/..."
                  />
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Submission note</label>
                  <textarea
                    v-model="submissionNote"
                    rows="3"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                    placeholder="What did you do? Any instructions for review?"
                  />
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Files (optional, one per line)</label>
                  <textarea
                    v-model="submissionFilesText"
                    rows="2"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                    placeholder="drive link or filename"
                  />
                </div>

                <button
                  type="button"
                  class="inline-flex items-center rounded-full bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow-[0_0_24px_rgba(139,124,255,0.65)] hover:opacity-95"
                  @click="submitForReview"
                >
                  Submit for review
                </button>
              </div>

              <div v-else class="text-xs text-white/40">
                You can view submissions, but you cannot submit this task.
              </div>
            </div>

            <!-- Review -->
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-white/50">
                Review
              </div>

              <div v-if="canReview" class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                  <div class="space-y-1">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Decision</label>
                    <select
                      v-model="reviewStatus"
                      class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                    >
                      <option value="Approved">Approved</option>
                      <option value="Changes Requested">Changes Requested</option>
                      <option value="Rejected">Rejected</option>
                    </select>
                  </div>

                  <div class="space-y-1">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Reviewer</label>
                    <div class="rounded-lg border border-white/10 bg-slate-950/50 px-3 py-2 text-xs text-white/70">
                      {{ authUser?.name ?? '—' }}
                    </div>
                  </div>
                </div>

                <div class="space-y-1">
                  <label class="block text-[11px] font-semibold uppercase tracking-wide text-white/50">Comment (optional)</label>
                  <textarea
                    v-model="reviewComment"
                    rows="3"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-white placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
                    placeholder="Feedback for the assignee"
                  />
                </div>

                <button
                  type="button"
                  class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold text-white/80 hover:bg-white/10"
                  @click="reviewTask"
                >
                  Save review
                </button>
              </div>

              <div v-else class="text-xs text-white/40">
                You can view the review status, but you cannot review this task.
              </div>
            </div>

            <!-- Comments -->
            <div v-if="normalizedComments.length" class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-white/50">
                Comments
              </div>

              <div class="space-y-2">
                <div
                  v-for="(c, idx) in normalizedComments"
                  :key="idx"
                  class="rounded-xl border border-white/10 bg-slate-950/50 p-3"
                >
                  <div class="flex items-center justify-between gap-2 text-[11px] text-white/50">
                    <div class="truncate">
                      <span v-if="typeof c.by === 'string'">{{ c.by }}</span>
                      <span v-else-if="c.by && typeof c.by === 'object'">{{ c.by.name ?? '—' }}</span>
                      <span v-else>—</span>
                    </div>
                    <div class="shrink-0">
                      {{ c.at ? new Date(c.at).toLocaleString() : '' }}
                    </div>
                  </div>
                  <div class="mt-1 text-xs text-white/75 whitespace-pre-line">
                    {{ c.body ?? c.message }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-sm text-white/60">
            No task selected.
          </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-white/10 px-5 py-3">
          <div class="flex items-center justify-between">
            <button
              type="button"
              class="text-xs text-white/60 hover:text-white"
              @click="fetchTask"
              :disabled="!props.taskId"
            >
              Refresh
            </button>

            <div class="text-xs text-white/40">
              Esc to close
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
