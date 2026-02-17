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

type ClientContactPayload = {
  id: number
  client_id: number
  name: string
  email: string | null
  phone: string | null
  position: string | null
  is_primary: boolean
  has_portal_access?: boolean
}

type FinancialSummary = {
  total_budget_cents: number
  total_invoiced_cents: number
  currency: string
} | null

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
  tax_id?: string | null
  currency?: string | null
  status: string | null
  client_activation_status: string | null
  gbp_status?: string | null
  gbp_access?: string | null
  tags: string[] | null
  fronter: (number | string)[] | null
  closer: (number | string)[] | null
  assigned_account_manager_id: number | null
  account_manager?: AccountManager | null
  notes_sales: string | null
  notes_cst: string | null
  notes_tech: string | null
  projects?: ProjectSummary[] | null
  contacts?: ClientContactPayload[] | null
}

const props = defineProps<{
  client: ClientPayload
  organizationSlug: string
  financial_summary?: FinancialSummary
}>()

type TabId = 'overview' | 'projects' | 'contacts' | 'notes'
const activeTab = ref<TabId>('overview')

type NoteField = 'notes_sales' | 'notes_cst' | 'notes_tech'
const editingNotes = ref(false)
const activeNoteField = ref<NoteField>('notes_sales')
const noteTextarea = ref<HTMLTextAreaElement | null>(null)

const notesForm = useForm({
  company_name: props.client.company_name ?? '',
  primary_contact_name: props.client.primary_contact_name ?? '',
  primary_contact_email: props.client.primary_contact_email ?? '',
  notes_sales: props.client.notes_sales ?? '',
  notes_cst: props.client.notes_cst ?? '',
  notes_tech: props.client.notes_tech ?? '',
})

const noteLabel = computed(() => {
  switch (activeNoteField.value) {
    case 'notes_sales': return 'Sales'
    case 'notes_cst': return 'CST'
    case 'notes_tech': return 'Tech'
    default: return 'Notes'
  }
})

const activeNoteValue = computed<string>({
  get() {
    if (activeNoteField.value === 'notes_sales') return notesForm.notes_sales ?? ''
    if (activeNoteField.value === 'notes_cst') return notesForm.notes_cst ?? ''
    return notesForm.notes_tech ?? ''
  },
  set(v) {
    if (activeNoteField.value === 'notes_sales') notesForm.notes_sales = v
    if (activeNoteField.value === 'notes_cst') notesForm.notes_cst = v
    if (activeNoteField.value === 'notes_tech') notesForm.notes_tech = v
  },
})

function openNotesEditor(field: NoteField) {
  activeNoteField.value = field
  notesForm.clearErrors()
  notesForm.company_name = props.client.company_name ?? ''
  notesForm.primary_contact_name = props.client.primary_contact_name ?? ''
  notesForm.primary_contact_email = props.client.primary_contact_email ?? ''
  notesForm.notes_sales = props.client.notes_sales ?? ''
  notesForm.notes_cst = props.client.notes_cst ?? ''
  notesForm.notes_tech = props.client.notes_tech ?? ''
  editingNotes.value = true
  nextTick(() => noteTextarea.value?.focus())
}

function closeNotesEditor() {
  editingNotes.value = false
  notesForm.clearErrors()
}

function saveNotes() {
  notesForm.put(
    r('clients.update', { organization: props.organizationSlug, client: props.client.id }),
    { preserveScroll: true, onSuccess: () => closeNotesEditor() },
  )
}

const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

function destroyClient() {
  if (!confirm('Are you sure you want to permanently delete this client? This cannot be undone.')) return
  router.delete(r('clients.destroy', { organization: props.organizationSlug, client: props.client.id }))
}

const getStatusClass = (status: string | null) => {
  const s = (status || 'unknown').toLowerCase()
  const base = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '
  switch (s) {
    case 'active': return base + 'bg-emerald-900/50 text-emerald-300'
    case 'lead': case 'pending': return base + 'bg-indigo-900/50 text-indigo-300'
    case 'inactive': case 'lost': return base + 'bg-rose-900/40 text-rose-300'
    default: return base + 'bg-white/10 text-white/70'
  }
}

const contacts = computed(() => props.client.contacts ?? [])
const projects = computed(() => props.client.projects ?? [])

// Contact modal: null = add new, otherwise edit that contact
const contactModalOpen = ref(false)
const editingContact = ref<ClientContactPayload | null>(null)

const contactForm = useForm({
  name: '',
  email: '',
  phone: '',
  position: '',
  is_primary: false,
})

function openAddContact() {
  editingContact.value = null
  contactForm.reset()
  contactForm.clearErrors()
  contactForm.name = ''
  contactForm.email = ''
  contactForm.phone = ''
  contactForm.position = ''
  contactForm.is_primary = false
  contactModalOpen.value = true
}

function openEditContact(c: ClientContactPayload) {
  editingContact.value = c
  contactForm.reset()
  contactForm.clearErrors()
  contactForm.name = c.name
  contactForm.email = c.email ?? ''
  contactForm.phone = c.phone ?? ''
  contactForm.position = c.position ?? ''
  contactForm.is_primary = c.is_primary
  contactModalOpen.value = true
}

function closeContactModal() {
  contactModalOpen.value = false
  editingContact.value = null
  contactForm.clearErrors()
}

function submitContact() {
  if (editingContact.value) {
    contactForm.put(
      r('clients.contacts.update', {
        organization: props.organizationSlug,
        client: props.client.id,
        contact: editingContact.value.id,
      }),
      { preserveScroll: true, onSuccess: () => closeContactModal() },
    )
  } else {
    contactForm.post(
      r('clients.contacts.store', { organization: props.organizationSlug, client: props.client.id }),
      { preserveScroll: true, onSuccess: () => closeContactModal() },
    )
  }
}

function enablePortal(c: ClientContactPayload) {
  if (!c.email) return
  router.post(
    r('clients.contacts.portal.store', {
      organization: props.organizationSlug,
      client: props.client.id,
      contact: c.id,
    }),
    {},
    { preserveScroll: true },
  )
}

function deleteContact(c: ClientContactPayload) {
  if (!confirm('Remove this contact?')) return
  router.delete(
    r('clients.contacts.destroy', {
      organization: props.organizationSlug,
      client: props.client.id,
      contact: c.id,
    }),
    { preserveScroll: true },
  )
}

const contactModalTitle = computed(() => (editingContact.value ? 'Edit contact' : 'Add contact'))
const hasProjects = () => projects.value.length > 0
const firstTasks = (p: ProjectSummary) => (p.tasks ?? []).slice(0, 3)

function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(cents / 100)
}
</script>

<template>
  <div class="min-h-screen bg-slate-950">
    <!-- Header: Name, Website link, Status badge -->
    <div class="border-b border-white/10 bg-slate-950/60">
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
              <Link
                :href="r('clients.index', { organization: organizationSlug })"
                class="text-white/60 hover:text-white text-sm"
              >
                ← Clients
              </Link>
              <span
                v-if="client.status"
                :class="getStatusClass(client.status)"
              >
                {{ client.status }}
              </span>
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-white">
              {{ client.company_name }}
            </h1>
            <p class="mt-1 text-sm text-white/60 flex items-center gap-2 flex-wrap">
              <span>Client ID: {{ client.id }}</span>
              <a
                v-if="client.website"
                :href="client.website"
                target="_blank"
                rel="noreferrer"
                class="text-indigo-300 hover:text-indigo-200"
              >
                {{ client.website }}
              </a>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <Link
              :href="r('clients.edit', { organization: organizationSlug, client: client.id })"
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

      <!-- Tabs -->
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-1 -mb-px">
          <button
            v-for="t in [
              { id: 'overview' as TabId, label: 'Overview' },
              { id: 'projects' as TabId, label: 'Projects' },
              { id: 'contacts' as TabId, label: 'Contacts' },
              { id: 'notes' as TabId, label: 'Notes' },
            ]"
            :key="t.id"
            type="button"
            :class="[
              'px-4 py-3 text-sm font-medium border-b-2 transition',
              activeTab === t.id
                ? 'border-indigo-500 text-indigo-300'
                : 'border-transparent text-white/60 hover:text-white/80 hover:border-white/20'
            ]"
            @click="activeTab = t.id"
          >
            {{ t.label }}
          </button>
        </nav>
      </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <!-- Tab: Overview -->
      <div v-show="activeTab === 'overview'" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <h2 class="text-lg font-medium text-white mb-3">Address & details</h2>
            <dl class="space-y-2 text-sm">
              <div><dt class="text-white/50">Address</dt><dd class="text-white/80">{{ client.address || '—' }}</dd></div>
              <div><dt class="text-white/50">Tax ID</dt><dd class="text-white/80">{{ client.tax_id || '—' }}</dd></div>
              <div><dt class="text-white/50">Currency</dt><dd class="text-white/80">{{ client.currency || 'USD' }}</dd></div>
            </dl>
          </section>
          <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <h2 class="text-lg font-medium text-white mb-3">Primary contact</h2>
            <dl class="space-y-2 text-sm">
              <div><dt class="text-white/50">Name</dt><dd class="text-white/80">{{ client.primary_contact_name || '—' }}</dd></div>
              <div><dt class="text-white/50">Email</dt><dd class="text-white/80">
                <a v-if="client.primary_contact_email" :href="`mailto:${client.primary_contact_email}`" class="text-indigo-300 hover:text-indigo-200">{{ client.primary_contact_email }}</a>
                <span v-else>—</span>
              </dd></div>
              <div><dt class="text-white/50">Phone</dt><dd class="text-white/80">{{ client.primary_contact_phone || '—' }}</dd></div>
            </dl>
          </section>
        </div>
        <!-- Financial summary (only when permitted) -->
        <section v-if="financial_summary" class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
          <h2 class="text-lg font-medium text-white mb-3">Financial summary</h2>
          <div class="flex flex-wrap gap-6 text-sm">
            <div>
              <span class="text-white/50">Total budget</span>
              <p class="text-white font-medium">{{ formatMoney(financial_summary.total_budget_cents, financial_summary.currency) }}</p>
            </div>
            <div>
              <span class="text-white/50">Total invoiced</span>
              <p class="text-white font-medium">{{ formatMoney(financial_summary.total_invoiced_cents, financial_summary.currency) }}</p>
            </div>
          </div>
        </section>
      </div>

      <!-- Tab: Projects -->
      <div v-show="activeTab === 'projects'" class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-medium text-white">Projects</h2>
            <Link :href="r('projects.index', { organization: organizationSlug })" class="text-sm text-indigo-300 hover:text-indigo-200">View all</Link>
          </div>
          <p v-if="!hasProjects()" class="text-sm text-white/60">No projects yet for this client.</p>
          <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="project in projects"
              :key="project.id"
              class="rounded-xl border border-white/10 bg-slate-900/40 p-4"
            >
              <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-white truncate">{{ project.title }}</h3>
                    <span v-if="project.status" class="inline-flex items-center rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70">{{ project.status }}</span>
                  </div>
                  <p class="mt-1 text-xs text-white/50">Project ID: {{ project.id }}</p>
                </div>
                <Link
                  :href="r('projects.show', { organization: organizationSlug, project: project.id })"
                  class="text-sm text-indigo-300 hover:text-indigo-200 whitespace-nowrap"
                >
                  Open →
                </Link>
              </div>
              <div class="mt-3">
                <p class="text-[11px] uppercase tracking-wide text-white/40">Top tasks</p>
                <ul class="mt-2 space-y-1">
                  <li v-for="t in firstTasks(project)" :key="t.id" class="flex items-center justify-between gap-3 text-sm">
                    <span class="truncate text-white/80">{{ t.title }}</span>
                    <span class="text-xs text-white/50 whitespace-nowrap">{{ t.status || '—' }}</span>
                  </li>
                  <li v-if="(project.tasks ?? []).length === 0" class="text-sm text-white/60">No tasks yet.</li>
                </ul>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Tab: Contacts -->
      <div v-show="activeTab === 'contacts'" class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-medium text-white">Contacts</h2>
            <button
              type="button"
              class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
              @click="openAddContact"
            >
              Add Contact
            </button>
          </div>
          <p v-if="contacts.length === 0" class="text-sm text-white/60">No contacts yet.</p>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-white/50 border-b border-white/10">
                  <th class="pb-2 pr-4">Name</th>
                  <th class="pb-2 pr-4">Email</th>
                  <th class="pb-2 pr-4">Position</th>
                  <th class="pb-2 pr-4">Primary</th>
                  <th class="pb-2 w-24">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in contacts" :key="c.id" class="border-b border-white/5">
                  <td class="py-2 pr-4 text-white/90">{{ c.name }}</td>
                  <td class="py-2 pr-4">
                    <a v-if="c.email" :href="`mailto:${c.email}`" class="text-indigo-300 hover:text-indigo-200">{{ c.email }}</a>
                    <span v-else>—</span>
                  </td>
                  <td class="py-2 pr-4 text-white/80">{{ c.position || '—' }}</td>
                  <td class="py-2 pr-4"><span v-if="c.is_primary" class="text-emerald-400 text-xs">Primary</span><span v-else>—</span></td>
                  <td class="py-2 pr-4">
                    <div class="flex items-center gap-2">
                      <span
                        v-if="c.has_portal_access"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-900/50 px-2 py-0.5 text-xs text-emerald-300"
                        title="Has portal access"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Portal
                      </span>
                      <button
                        v-else-if="c.email"
                        type="button"
                        class="rounded-lg p-1.5 text-white/60 hover:text-amber-300 hover:bg-amber-500/10 transition"
                        title="Enable portal access"
                        @click="enablePortal(c)"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                      </button>
                      <button
                        type="button"
                        class="rounded-lg p-1.5 text-white/60 hover:text-white hover:bg-white/10 transition"
                        title="Edit"
                        @click="openEditContact(c)"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                      </button>
                      <button
                        type="button"
                        class="rounded-lg p-1.5 text-white/60 hover:text-rose-300 hover:bg-rose-500/10 transition"
                        title="Delete"
                        @click="deleteContact(c)"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Tab: Notes -->
      <div v-show="activeTab === 'notes'" class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
          <h2 class="text-lg font-medium text-white mb-4">Department notes</h2>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3">
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Sales</h3>
                <button type="button" class="text-xs font-medium text-indigo-300 hover:text-indigo-200" @click="openNotesEditor('notes_sales')">Edit</button>
              </div>
              <p class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]">{{ client.notes_sales || 'No notes yet' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3">
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-white/60">CST</h3>
                <button type="button" class="text-xs font-medium text-indigo-300 hover:text-indigo-200" @click="openNotesEditor('notes_cst')">Edit</button>
              </div>
              <p class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]">{{ client.notes_cst || 'No notes yet' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3">
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-white/60">Tech</h3>
                <button type="button" class="text-xs font-medium text-indigo-300 hover:text-indigo-200" @click="openNotesEditor('notes_tech')">Edit</button>
              </div>
              <p class="text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]">{{ client.notes_tech || 'No notes yet' }}</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <Modal :show="editingNotes" @close="closeNotesEditor" maxWidth="2xl">
    <div class="p-6">
      <h2 class="text-lg font-semibold text-white">{{ noteLabel }} notes</h2>
      <p class="mt-1 text-sm text-white/60">Update department notes for this client.</p>
      <div class="mt-4">
        <textarea
          ref="noteTextarea"
          v-model="activeNoteValue"
          rows="8"
          class="w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/80 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          placeholder="Write notes…"
        />
        <p v-if="notesForm.errors[activeNoteField]" class="mt-2 text-sm text-red-400">{{ notesForm.errors[activeNoteField] }}</p>
      </div>
      <div class="mt-6 flex items-center justify-end gap-2">
        <SecondaryButton type="button" @click="closeNotesEditor">Cancel</SecondaryButton>
        <PrimaryButton type="button" :disabled="notesForm.processing" @click="saveNotes">Save</PrimaryButton>
      </div>
    </div>
  </Modal>

  <Modal :show="contactModalOpen" @close="closeContactModal" maxWidth="lg">
    <div class="p-6">
      <h2 class="text-lg font-semibold text-white">{{ contactModalTitle }}</h2>
      <p class="mt-1 text-sm text-white/60">Name is required. Other fields are optional.</p>
      <form class="mt-4 space-y-4" @submit.prevent="submitContact">
        <div>
          <label class="block text-xs font-medium text-white/60 mb-1">Name</label>
          <input
            v-model="contactForm.name"
            type="text"
            required
            class="w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Full name"
          >
          <p v-if="contactForm.errors.name" class="mt-1 text-sm text-red-400">{{ contactForm.errors.name }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-white/60 mb-1">Email</label>
          <input
            v-model="contactForm.email"
            type="email"
            class="w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="email@example.com"
          >
          <p v-if="contactForm.errors.email" class="mt-1 text-sm text-red-400">{{ contactForm.errors.email }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-white/60 mb-1">Phone</label>
          <input
            v-model="contactForm.phone"
            type="text"
            class="w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="+1 234 567 8900"
          >
          <p v-if="contactForm.errors.phone" class="mt-1 text-sm text-red-400">{{ contactForm.errors.phone }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-white/60 mb-1">Position</label>
          <input
            v-model="contactForm.position"
            type="text"
            class="w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Job title"
          >
          <p v-if="contactForm.errors.position" class="mt-1 text-sm text-red-400">{{ contactForm.errors.position }}</p>
        </div>
        <div class="flex items-center gap-2">
          <input
            id="contact-primary"
            v-model="contactForm.is_primary"
            type="checkbox"
            class="h-4 w-4 rounded border-white/20 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
          >
          <label for="contact-primary" class="text-sm text-white/80">Primary contact</label>
        </div>
        <div class="mt-6 flex items-center justify-end gap-2">
          <SecondaryButton type="button" @click="closeContactModal">Cancel</SecondaryButton>
          <PrimaryButton type="submit" :disabled="contactForm.processing">
            {{ contactForm.processing ? 'Saving…' : 'Save' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>
