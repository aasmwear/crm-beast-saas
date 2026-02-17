<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineOptions({ layout: PortalLayout })

type ProjectFile = {
  id: number
  filename: string
  path: string
  mime_type: string | null
  size: number
}

const props = defineProps<{
  project: {
    id: number
    title: string
    status: string | null
    description: string | null
    start_date: string | null
    end_date: string | null
    tasks: Array<{
      id: number
      title: string
      status: string | null
      due_date: string | null
    }>
    files?: ProjectFile[]
  }
}>()

const r = (name: string, params: Record<string, number> = {}) =>
  (window as any).route ? (window as any).route(name, params) : '#'

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString()
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function getFileIcon(mime: string | null): string {
  if (!mime) return '📄'
  const m = (mime || '').toLowerCase()
  if (m.startsWith('image/')) return '🖼️'
  if (m.includes('pdf')) return '📕'
  if (m.includes('sheet') || m.includes('excel')) return '📊'
  if (m.includes('word') || m.includes('document')) return '📘'
  return '📄'
}

const visibleFiles = () => props.project.files ?? []
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <Link
        :href="r('portal.dashboard')"
        class="text-sm text-white/60 hover:text-white"
      >
        ← My Projects
      </Link>
    </div>

    <section class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-xl font-semibold text-white">
            {{ project.title }}
          </h1>
          <div class="mt-2 flex flex-wrap gap-3 text-sm text-white/60">
            <span>Status: {{ project.status || '—' }}</span>
            <span>Start: {{ formatDate(project.start_date) }}</span>
            <span>End: {{ formatDate(project.end_date) }}</span>
          </div>
        </div>
      </div>
      <p v-if="project.description" class="mt-4 text-sm text-white/80 whitespace-pre-wrap">
        {{ project.description }}
      </p>
    </section>

    <section
      v-if="visibleFiles().length > 0"
      class="rounded-2xl border border-white/10 bg-slate-900/40 p-6"
    >
      <h2 class="text-lg font-medium text-white mb-4">Files</h2>
      <div class="space-y-2">
        <a
          v-for="f in visibleFiles()"
          :key="f.id"
          :href="r('portal.projects.files.download', { project: project.id, projectFile: f.id })"
          class="flex items-center gap-3 rounded-lg border border-white/5 bg-slate-800/30 px-4 py-3 text-sm text-white/90 hover:bg-slate-800/50 transition-colors"
        >
          <span class="text-lg">{{ getFileIcon(f.mime_type) }}</span>
          <span class="flex-1 truncate">{{ f.filename }}</span>
          <span class="text-xs text-white/50">{{ formatFileSize(f.size) }}</span>
          <span class="text-white/60">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
          </span>
        </a>
      </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
      <h2 class="text-lg font-medium text-white mb-4">Tasks</h2>
      <div v-if="!project.tasks || project.tasks.length === 0" class="text-sm text-white/60">
        No tasks for this project.
      </div>
      <ul v-else class="space-y-2">
        <li
          v-for="task in project.tasks"
          :key="task.id"
          class="flex items-center justify-between rounded-lg border border-white/5 bg-slate-800/30 px-4 py-3 text-sm"
        >
          <span class="text-white/90">{{ task.title }}</span>
          <div class="flex items-center gap-3 text-white/50">
            <span>{{ task.status || '—' }}</span>
            <span>{{ formatDate(task.due_date) }}</span>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
