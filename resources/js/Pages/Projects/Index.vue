<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/ui/Card.vue'
import EmptyState from '@/Components/ui/EmptyState.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import ProgressBar from '@/Components/ui/ProgressBar.vue'

defineOptions({ layout: AuthenticatedLayout })

type TeamAvatar = { id: number; name: string }

type ProjectCard = {
  id: number
  title: string
  description: string | null
  status: string
  client_name: string | null
  progress: number
  due_date: string | null
  due_date_formatted: string | null
  team: { avatars: TeamAvatar[]; extra: number }
}

type Client = { id: number; company_name: string }
type UserOption = { id: number; name: string }

const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, unknown> = {}) =>
  routeGlobal ? routeGlobal(name, params) : '#'

const props = defineProps<{
  organization: { id: number; name: string; slug: string }
  projects: ProjectCard[]
  clients: Client[]
  users: UserOption[]
}>()

const orgSlug = computed(() => props.organization?.slug ?? 'acme')

const showingCreateModal = ref(false)

function openCreateModal() {
  createForm.reset()
  showingCreateModal.value = true
}

function closeCreateModal() {
  showingCreateModal.value = false
}

const createForm = useForm({
  title: '',
  description: '',
  client_id: null as number | null,
  status: 'Not Started' as string,
  due_date: '' as string,
  manager_id: null as number | null,
})

function submitCreate() {
  createForm.transform((data) => ({
    title: data.title,
    description: data.description,
    client_id: data.client_id,
    status: data.status,
    due_date: data.due_date || null,
    user_ids: data.manager_id ? [data.manager_id] : [],
  })).post(r('projects.store', { organization: orgSlug.value }), {
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
    },
  })
}

function statusClass(status: string): string {
  const s = (status || '').toLowerCase()
  if (s.includes('completed')) return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40'
  if (s.includes('progress') || s.includes('active')) return 'bg-blue-500/15 text-blue-300 border border-blue-500/40'
  if (s.includes('hold')) return 'bg-amber-500/15 text-amber-300 border border-amber-500/40'
  if (s.includes('not started')) return 'bg-zinc-500/15 text-zinc-400 border border-zinc-500/40'
  return 'bg-white/10 text-white/70 border border-white/10'
}

function isDuePassed(dueDate: string | null): boolean {
  if (!dueDate) return false
  const d = new Date(dueDate)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  d.setHours(0, 0, 0, 0)
  return d < today
}

function projectUrl(project: ProjectCard): string {
  return r('projects.show', { organization: orgSlug.value, project: project.id })
}
</script>

<template>
  <div class="space-y-6">
    <section class="hero-slab">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-white">
            Projects
          </h1>
          <p class="mt-1 text-sm text-white/60">
            Manage and track all organizational projects.
          </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <a
            :href="r('projects.index', { organization: orgSlug })"
            class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium bg-white/10 text-white border border-white/10"
          >
            List
          </a>
          <a
            :href="r('projects.board', { organization: orgSlug })"
            class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white border border-transparent hover:border-white/10 transition"
          >
            Board
          </a>
          <a
            :href="r('projects.calendar', { organization: orgSlug })"
            class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white border border-transparent hover:border-white/10 transition"
          >
            Calendar
          </a>
          <button
            type="button"
            @click="openCreateModal"
            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold bg-[var(--primary)] text-white hover:opacity-90 transition shadow-lg shadow-[var(--primary)]/20"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Project
          </button>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <a
        v-for="project in projects"
        :key="project.id"
        :href="projectUrl(project)"
        class="block text-left rounded-2xl overflow-hidden hover:ring-2 hover:ring-[var(--primary)]/50 hover:shadow-xl hover:shadow-[var(--primary)]/5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--primary)]/50 group"
      >
        <Card class="h-full rounded-2xl border border-white/10 bg-white/5 hover:border-[var(--primary)]/30 transition-colors p-5">
          <div class="flex items-start justify-between gap-2 mb-4">
            <span class="text-xs font-bold uppercase tracking-wider text-white/40 truncate flex-1 min-w-0">
              {{ project.client_name ?? 'Internal' }}
            </span>
            <span
              class="shrink-0 inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border"
              :class="statusClass(project.status)"
            >
              {{ project.status }}
            </span>
          </div>

          <h2 class="text-lg font-bold text-white mb-4 line-clamp-2 group-hover:text-[var(--primary)] transition-colors">
            {{ project.title }}
          </h2>

          <div class="mb-6">
            <div class="flex items-center justify-between text-xs text-white/50 mb-1.5">
              <span>Progress</span>
              <span class="font-mono text-white/70">{{ project.progress }}%</span>
            </div>
            <ProgressBar :value="project.progress" />
          </div>

          <div class="mt-auto flex items-center justify-between gap-2 pt-4 border-t border-white/5">
            <div class="flex -space-x-2">
              <div
                v-for="member in project.team.avatars"
                :key="member.id"
                class="h-8 w-8 rounded-full border-2 border-slate-900 bg-gradient-to-br from-[var(--primary)] to-violet-600 flex items-center justify-center text-xs font-bold text-white shrink-0"
                :title="member.name"
              >
                {{ member.name.charAt(0).toUpperCase() }}
              </div>
              <div
                v-if="project.team.extra > 0"
                class="h-8 w-8 rounded-full border-2 border-slate-900 bg-slate-700 flex items-center justify-center text-xs text-white/70 shrink-0"
              >
                +{{ project.team.extra }}
              </div>
              <div v-if="project.team.avatars.length === 0" class="text-xs text-white/30 italic pl-1">
                 No team
              </div>
            </div>
            <span
              class="text-xs font-medium flex items-center gap-1 shrink-0"
              :class="isDuePassed(project.due_date) ? 'text-red-400' : 'text-white/40'"
            >
              <span v-if="project.due_date">📅</span>
              {{ project.due_date_formatted ?? '' }}
            </span>
          </div>
        </Card>
      </a>
    </div>

    <EmptyState
      v-if="!projects.length"
      title="No Projects Yet"
      description="Start your first project to get organized."
      icon="📂"
    >
      <template #action>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[var(--primary)]/20 hover:opacity-90 transition"
          @click="openCreateModal"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Start your first project
        </button>
      </template>
    </EmptyState>

    <Modal :show="showingCreateModal" max-width="lg" @close="closeCreateModal">
      <div class="p-6 text-left text-white">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold">Create Project</h2>
            <button @click="closeCreateModal" class="text-white/40 hover:text-white transition">&times;</button>
        </div>
        
        <p class="mb-6 text-sm text-white/50">
          Add a new project. You can assign a manager and set a due date.
        </p>

        <form @submit.prevent="submitCreate" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-white/60 mb-1.5">Title</label>
            <input
              v-model="createForm.title"
              type="text"
              required
              placeholder="Project name"
              class="w-full rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white placeholder-white/30 shadow-sm focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-colors"
              :class="{ 'border-red-500/50 focus:border-red-500 focus:ring-red-500': createForm.errors.title }"
            />
            <p v-if="createForm.errors.title" class="mt-1 text-xs text-red-400">
              {{ createForm.errors.title }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-white/60 mb-1.5">Client</label>
            <select
              v-model="createForm.client_id"
              class="w-full rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white shadow-sm focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-colors"
            >
              <option :value="null" class="text-white/50">— No client —</option>
              <option
                v-for="c in clients"
                :key="c.id"
                :value="c.id"
              >
                {{ c.company_name }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-white/60 mb-1.5">Status</label>
                <select
                  v-model="createForm.status"
                  class="w-full rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white shadow-sm focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-colors"
                >
                  <option value="Not Started">Not Started</option>
                  <option value="In Progress">In Progress</option>
                  <option value="On Hold">On Hold</option>
                  <option value="Completed">Completed</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-white/60 mb-1.5">Due Date</label>
                <input
                  v-model="createForm.due_date"
                  type="date"
                  class="w-full rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white shadow-sm focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-colors [color-scheme:dark]"
                />
              </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-white/60 mb-1.5">Manager</label>
            <select
              v-model="createForm.manager_id"
              class="w-full rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white shadow-sm focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-colors"
            >
              <option :value="null" class="text-white/50">— No manager —</option>
              <option
                v-for="u in users"
                :key="u.id"
                :value="u.id"
              >
                {{ u.name }}
              </option>
            </select>
            <p class="mt-1 text-xs text-white/40">Project lead for this project.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-white/60 mb-1.5">Description</label>
            <textarea
              v-model="createForm.description"
              rows="3"
              placeholder="Brief description (optional)"
              class="w-full rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-sm text-white placeholder-white/30 shadow-sm focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] resize-none transition-colors"
            />
          </div>

          <div class="flex justify-end gap-3 pt-6 border-t border-white/10">
            <SecondaryButton 
                type="button" 
                @click="closeCreateModal"
                class="!bg-white/5 !text-white !border-white/10 hover:!bg-white/10"
            >
              Cancel
            </SecondaryButton>
            <PrimaryButton
              type="submit"
              :disabled="createForm.processing"
              class="!bg-[var(--primary)] !border-0 !text-white hover:!opacity-90 shadow-lg shadow-[var(--primary)]/20"
            >
              {{ createForm.processing ? 'Creating…' : 'Create Project' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </div>
</template>
