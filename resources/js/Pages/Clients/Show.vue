<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { computed, nextTick, ref } from 'vue'

defineOptions({ layout: AuthenticatedLayout })

type AccountManager = {
  id: number
  name: string
}

type TaskSummary = {
  id: number
  title: string
  status: string | null
  due_date: string | null
}

type ProjectSummary = {
  id: number
  title: string
  status: string | null
  tasks?: TaskSummary[] | null
}

type ClientPayload = {
  id: number
  organization_id: number
  company_name: string | null
  industry: string | null
  niche: string | null
  primary_contact_name: string | null
  primary_contact_email: string | null
  primary_contact_phone: string | null
  website: string | null
  address: string | null
  status: string | null
  client_activation_status: string | null
  google_business_profile_status: string | null
  google_business_profile_access_status: string | null
  tags: string[] | null
  fronter: (number | string)[] | null
  closer: (number | string)[] | null
  assigned_account_manager_id: number | null
  account_manager?: AccountManager | null
  // Internal notes (by role)
  notes_by_sales: string | null
  notes_by_cst: string | null
  notes_by_tech: string | null
  // Related projects + tasks (via eager-loaded relation)
  projects?: ProjectSummary[] | null
}

const props = defineProps<{
  client: ClientPayload
  organizationSlug: string
}>()

type NoteField = 'notes_by_sales' | 'notes_by_cst' | 'notes_by_tech'

const editingNotes = ref(false)
const activeNoteField = ref<NoteField>('notes_by_sales')
const noteTextarea = ref<HTMLTextAreaElement | null>(null)

const notesForm = useForm({
  company_name: props.client.company_name ?? '',
  primary_contact_name: props.client.primary_contact_name ?? '',
  primary_contact_email: props.client.primary_contact_email ?? '',
  notes_by_sales: props.client.notes_by_sales ?? '',
  notes_by_cst: props.client.notes_by_cst ?? '',
  notes_by_tech: props.client.notes_by_tech ?? '',
})

const noteLabel = computed(() => {
  switch (activeNoteField.value) {
    case 'notes_by_sales':
      return 'Sales'
    case 'notes_by_cst':
      return 'CST'
    case 'notes_by_tech':
      return 'Tech'
    default:
      return 'Notes'
  }
})

const activeNoteValue = computed<string>({
  get() {
    if (activeNoteField.value === 'notes_by_sales') return notesForm.notes_by_sales ?? ''
    if (activeNoteField.value === 'notes_by_cst') return notesForm.notes_by_cst ?? ''
    return notesForm.notes_by_tech ?? ''
  },
  set(v) {
    if (activeNoteField.value === 'notes_by_sales') notesForm.notes_by_sales = v
    if (activeNoteField.value === 'notes_by_cst') notesForm.notes_by_cst = v
    if (activeNoteField.value === 'notes_by_tech') notesForm.notes_by_tech = v
  },
})

const openNotesEditor = (field: NoteField) => {
  activeNoteField.value = field
  notesForm.clearErrors()

  // Refresh values from latest props (Inertia may have updated them after saves)
  notesForm.company_name = props.client.company_name ?? ''
  notesForm.primary_contact_name = props.client.primary_contact_name ?? ''
  notesForm.primary_contact_email = props.client.primary_contact_email ?? ''
  notesForm.notes_by_sales = props.client.notes_by_sales ?? ''
  notesForm.notes_by_cst = props.client.notes_by_cst ?? ''
  notesForm.notes_by_tech = props.client.notes_by_tech ?? ''

  editingNotes.value = true

  nextTick(() => {
    noteTextarea.value?.focus()
  })
}

const closeNotesEditor = () => {
  editingNotes.value = false
  notesForm.clearErrors()
}

const saveNotes = () => {
  notesForm.put(
    r('clients.update', {
      organization: props.organizationSlug,
      client: props.client.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => closeNotesEditor(),
    },
  )
}

// Safe Ziggy route helper
const routeGlobal =
  (window as any).route as
    | ((name: string, params?: any, absolute?: boolean, config?: any) => string)
    | undefined

const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

const destroyClient = () => {
  if (
    !confirm(
      'Are you sure you want to permanently delete this client? This cannot be undone.',
    )
  ) {
    return
  }

  router.delete(
    r('clients.destroy', {
      organization: props.organizationSlug,
      client: props.client.id,
    }),
  )
}

// Helper function to apply the status chip styling
const getStatusClass = (status: string | null) => {
  const s = (status || 'unknown').toLowerCase()
  const base =
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '

  switch (s) {
    case 'active':
      return base + 'bg-emerald-900/50 text-emerald-300'
    case 'lead':
    case 'pending':
      return base + 'bg-indigo-900/50 text-indigo-300'
    case 'inactive':
    case 'lost':
      return base + 'bg-rose-900/40 text-rose-300'
    case 'on hold':
    case 'on_hold':
      return base + 'bg-amber-900/40 text-amber-300'
    default:
      return base + 'bg-white/10 text-white/70'
  }
}

// Small helpers for display
const hasProjects = () => (props.client.projects ?? []).length > 0
const firstTasks = (p: ProjectSummary) => (p.tasks ?? []).slice(0, 3)
</script>

<template>
  <div class="min-h-screen bg-slate-950">
    <!-- Header -->
    <div class="border-b border-white/10 bg-slate-950/60">
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="min-w-0">
            <div class="flex items-center gap-3">
              <Link
                :href="r('clients.index', { organization: props.organizationSlug })"
                class="text-white/60 hover:text-white text-sm"
              >
                ← Clients
              </Link>

              <span
                v-if="props.client.status"
                :class="getStatusClass(props.client.status)"
              >
                {{ props.client.status }}
              </span>
            </div>

            <h1 class="mt-2 text-2xl font-semibold text-white">
              {{ props.client.company_name }}
            </h1>

            <p class="mt-1 text-sm text-white/60">
              Client ID: {{ props.client.id }} • Org: {{ props.client.organization_id }}
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Link
              :href="
                r('clients.edit', {
                  organization: props.organizationSlug,
                  client: props.client.id,
                })
              "
              class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/80 hover:bg-white/10"
            >
              Edit
            </Link>

            <button
              type="button"
              class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-200 hover:bg-rose-500/20"
              @click="destroyClient"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- Left / main -->
        <div class="lg:col-span-8 space-y-6">
          <!-- Projects -->
          <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-medium text-white">
                Projects
              </h2>

              <Link
                :href="r('projects.index', { organization: props.organizationSlug })"
                class="text-sm text-indigo-300 hover:text-indigo-200"
              >
                View all
              </Link>
            </div>

            <div v-if="!hasProjects()" class="mt-4 text-sm text-white/60">
              No projects yet for this client.
            </div>

            <div v-else class="mt-4 space-y-3">
              <div
                v-for="project in props.client.projects"
                :key="project.id"
                class="rounded-xl border border-white/10 bg-slate-900/40 p-4"
              >
                <div class="flex items-center justify-between gap-4">
                  <div class="min-w-0">
                    <div class="flex items-center gap-2">
                      <h3 class="text-sm font-semibold text-white truncate">
                        {{ project.title }}
                      </h3>

                      <span
                        v-if="project.status"
                        class="inline-flex items-center rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70"
                      >
                        {{ project.status }}
                      </span>
                    </div>

                    <p class="mt-1 text-xs text-white/50">
                      Project ID: {{ project.id }}
                    </p>
                  </div>

                  <Link
                    :href="
                      r('projects.show', {
                        organization: props.organizationSlug,
                        project: project.id,
                      })
                    "
                    class="text-sm text-indigo-300 hover:text-indigo-200 whitespace-nowrap"
                  >
                    Open →
                  </Link>
                </div>

                <div class="mt-3">
                  <p class="text-[11px] uppercase tracking-wide text-white/40">
                    Top tasks
                  </p>

                  <ul class="mt-2 space-y-1">
                    <li
                      v-for="t in firstTasks(project)"
                      :key="t.id"
                      class="flex items-center justify-between gap-3 text-sm"
                    >
                      <span class="truncate text-white/80">
                        {{ t.title }}
                      </span>
                      <span class="text-xs text-white/50 whitespace-nowrap">
                        {{ t.status || '—' }}
                      </span>
                    </li>

                    <li
                      v-if="(project.tasks ?? []).length === 0"
                      class="text-sm text-white/60"
                    >
                      No tasks yet.
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </section>

          <!-- Notes -->
          <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <h2 class="text-lg font-medium text-white">
              Department Notes
            </h2>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3">
                <div class="flex items-center justify-between mb-2">
                  <h3
                    class="text-[11px] font-semibold uppercase tracking-wide text-white/60 mb-2"
                  >Sales</h3>
                  <button
                    type="button"
                    class="text-xs font-medium text-indigo-300 hover:text-indigo-200"
                    @click="openNotesEditor('notes_by_sales')"
                  >
                    Edit
                  </button>
                </div>
                <p class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]">
                  {{ props.client.notes_by_sales || 'No notes yet' }}
                </p>
              </div>

              <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3">
                <div class="flex items-center justify-between mb-2">
                  <h3
                    class="text-[11px] font-semibold uppercase tracking-wide text-white/60 mb-2"
                  >CST</h3>
                  <button
                    type="button"
                    class="text-xs font-medium text-indigo-300 hover:text-indigo-200"
                    @click="openNotesEditor('notes_by_cst')"
                  >
                    Edit
                  </button>
                </div>
                <p class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]">
                  {{ props.client.notes_by_cst || 'No notes yet' }}
                </p>
              </div>

              <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3">
                <div class="flex items-center justify-between mb-2">
                  <h3
                    class="text-[11px] font-semibold uppercase tracking-wide text-white/60 mb-2"
                  >Tech</h3>
                  <button
                    type="button"
                    class="text-xs font-medium text-indigo-300 hover:text-indigo-200"
                    @click="openNotesEditor('notes_by_tech')"
                  >
                    Edit
                  </button>
                </div>
                <p class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]">
                  {{ props.client.notes_by_tech || 'No notes yet' }}
                </p>
              </div>
            </div>
          </section>
        </div>

        <!-- Right: primary contact & quick actions -->
        <aside class="lg:col-span-4 space-y-4">
          <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <h2 class="text-lg font-medium text-white mb-3">
              Primary
            </h2>

            <dl class="space-y-2 text-sm">
              <div class="flex">
                <dt class="w-20 text-white/50">Name</dt>
                <dd class="text-white/80">
                  {{ props.client.primary_contact_name || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-20 text-white/50">Email</dt>
                <dd class="text-white/80">
                  <a
                    v-if="props.client.primary_contact_email"
                    class="text-indigo-300 hover:text-indigo-200"
                    :href="`mailto:${props.client.primary_contact_email}`"
                  >
                    {{ props.client.primary_contact_email }}
                  </a>
                  <span v-else>—</span>
                </dd>
              </div>

              <div class="flex">
                <dt class="w-20 text-white/50">Phone</dt>
                <dd class="text-white/80">
                  {{ props.client.primary_contact_phone || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-20 text-white/50">Website</dt>
                <dd class="text-white/80">
                  <a
                    v-if="props.client.website"
                    class="text-indigo-300 hover:text-indigo-200"
                    :href="props.client.website"
                    target="_blank"
                    rel="noreferrer"
                  >
                    {{ props.client.website }}
                  </a>
                  <span v-else>—</span>
                </dd>
              </div>

              <div class="flex">
                <dt class="w-20 text-white/50">Address</dt>
                <dd class="text-white/80">
                  {{ props.client.address || '—' }}
                </dd>
              </div>
            </dl>
          </section>

          <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <h2 class="text-lg font-medium text-white mb-3">
              GBP
            </h2>

            <dl class="space-y-2 text-sm">
              <div class="flex">
                <dt class="w-20 text-white/50">Status</dt>
                <dd class="text-white/80">
                  {{ props.client.google_business_profile_status || '—' }}
                </dd>
              </div>

              <div class="flex">
                <dt class="w-20 text-white/50">Access</dt>
                <dd class="text-white/80">
                  {{ props.client.google_business_profile_access_status || '—' }}
                </dd>
              </div>
            </dl>
          </section>
        </aside>
      </div>
    </div>
  </div>

  <Modal :show="editingNotes" @close="closeNotesEditor" maxWidth="2xl">
    <div class="p-6">
      <h2 class="text-lg font-semibold text-white">
        {{ noteLabel }} Notes
      </h2>
      <p class="mt-1 text-sm text-white/60">
        Update department notes for this client. Changes are saved to the client record and logged in Activity.
      </p>

      <div class="mt-4">
        <textarea
          ref="noteTextarea"
          v-model="activeNoteValue"
          rows="8"
          class="w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/80 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          placeholder="Write notes…"
        />
        <p v-if="notesForm.errors[activeNoteField]" class="mt-2 text-sm text-red-400">
          {{ notesForm.errors[activeNoteField] }}
        </p>
      </div>

      <div class="mt-6 flex items-center justify-end gap-2">
        <SecondaryButton type="button" @click="closeNotesEditor">
          Cancel
        </SecondaryButton>

        <PrimaryButton type="button" :disabled="notesForm.processing" @click="saveNotes">
          Save
        </PrimaryButton>
      </div>
    </div>
  </Modal>
</template>
