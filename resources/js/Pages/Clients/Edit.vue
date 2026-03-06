<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

defineOptions({ layout: AuthenticatedLayout })

type UserBrief = {
  id: number
  name: string
}

type ClientResource = {
  id: number
  company_name: string
  industry: string | null
  niche: string | null
  website: string | null
  primary_contact_name: string | null
  primary_contact_email: string | null
  primary_contact_phone: string | null
  address: string | null

  fronter_id: number | null
  closer_id: number | null
  assigned_account_manager_id: number | null

  gbp_status: string | null
  gbp_access: string | null

  client_activation_status: string | null
  status: string | null

  notes_sales: string | null
  notes_cst: string | null
  notes_tech: string | null
}

type ClientForm = ClientResource & {
  new_note_sales: string
  new_note_cst: string
  new_note_tech: string
}

type PageProps = {
  tenant?: { slug: string }
  organization?: { slug: string }
}

// ---------- Route & props ----------
const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

const page = usePage<PageProps>()

const props = defineProps<{
  organizationSlug: string
  client: ClientResource
  users: UserBrief[]
}>()

const client = props.client

const org = computed(() => {
  if (props.organizationSlug) return props.organizationSlug
  const p = page.props
  return p.tenant?.slug ?? p.organization?.slug ?? 'acme'
})

// ---------- Form ----------
const form = useForm<ClientForm>({
  id: client.id,
  company_name: client.company_name ?? '',
  industry: client.industry ?? null,
  niche: client.niche ?? null,
  website: client.website ?? null,
  primary_contact_name: client.primary_contact_name ?? null,
  primary_contact_email: client.primary_contact_email ?? null,
  primary_contact_phone: client.primary_contact_phone ?? null,
  address: client.address ?? null,

  fronter_id: client.fronter_id ?? null,  // Single user ID (strict accountability)
  closer_id: client.closer_id ?? null,    // Single user ID (strict accountability)
  assigned_account_manager_id: client.assigned_account_manager_id ?? null,

  gbp_status: client.gbp_status ?? null,
  gbp_access: client.gbp_access ?? null,

  client_activation_status: client.client_activation_status ?? 'lead',
  status: client.status ?? 'lead',

  notes_sales: client.notes_sales ?? null,
  notes_cst: client.notes_cst ?? null,
  notes_tech: client.notes_tech ?? null,

  new_note_sales: '',
  new_note_cst: '',
  new_note_tech: '',
})

const submit = () => {
  form.put(
    r('clients.update', {
      organization: org.value,
      client: client.id,
    }),
  )
}

// ---------- UI helpers ----------
const inputClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'

const textareaClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'

const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1'

const statusOptions = [
  { value: 'lead', label: 'Lead' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'paused', label: 'Paused' },
  { value: 'churned', label: 'Churned' },
]

const activationOptions = [
  { value: 'lead', label: 'Lead (not started)' },
  { value: 'onboarding', label: 'Onboarding' },
  { value: 'active', label: 'Active' },
  { value: 'paused', label: 'Paused' },
  { value: 'churned', label: 'Churned / Lost' },
]

const gbpStatusOptions = [
  { value: '', label: '— Select GBP status —' },
  { value: 'not_created', label: 'Not Created' },
  { value: 'created', label: 'Created' },
  { value: 'pending', label: 'Pending' },
  { value: 'verified', label: 'Verified' },
  { value: 'suspended', label: 'Suspended' },
]

const gbpAccessOptions = [
  { value: '', label: '— Select access level —' },
  { value: 'no_access', label: 'No Access' },
  { value: 'access_pending', label: 'Access Pending' },
  { value: 'access_granted', label: 'Access Granted' },
]
</script>

<template>
  <PageShell
    :header="{
      breadcrumb: `Organization • ${org.toUpperCase()}`,
      title: 'Edit Client',
      subtitle: `Update details for ${client.company_name ?? 'this client'}.`,
    }"
  >
    <template #header-actions>
      <Link
        :href="r('clients.index', { organization: org })"
        class="btn-capsule text-xs"
      >
        Back to Clients
      </Link>
    </template>

    <!-- Form -->
    <form @submit.prevent="submit" class="card-neo p-6 space-y-8">
      <!-- Basic info -->
      <div class="space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Basic information
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label :class="labelClass">Company name *</label>
            <input
              v-model="form.company_name"
              :class="inputClass"
              required
            />
            <div
              v-if="form.errors.company_name"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.company_name }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Website</label>
            <input v-model="form.website" :class="inputClass" />
            <div
              v-if="form.errors.website"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.website }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Industry</label>
            <input v-model="form.industry" :class="inputClass" />
            <div
              v-if="form.errors.industry"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.industry }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Niche</label>
            <input v-model="form.niche" :class="inputClass" />
            <div v-if="form.errors.niche" class="mt-1 text-sm text-red-400">
              {{ form.errors.niche }}
            </div>
          </div>
        </div>
      </div>

      <!-- Contact -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Primary contact
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label :class="labelClass">Contact name</label>
            <input v-model="form.primary_contact_name" :class="inputClass" />
            <div
              v-if="form.errors.primary_contact_name"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.primary_contact_name }}
            </div>
          </div>
          <div>
            <label :class="labelClass">Contact email</label>
            <input
              v-model="form.primary_contact_email"
              :class="inputClass"
              type="email"
            />
            <div
              v-if="form.errors.primary_contact_email"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.primary_contact_email }}
            </div>
          </div>
          <div>
            <label :class="labelClass">Contact phone</label>
            <input v-model="form.primary_contact_phone" :class="inputClass" />
            <div
              v-if="form.errors.primary_contact_phone"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.primary_contact_phone }}
            </div>
          </div>
          <div>
            <label :class="labelClass">Address</label>
            <textarea
              v-model="form.address"
              rows="3"
              :class="textareaClass"
            ></textarea>
            <div v-if="form.errors.address" class="mt-1 text-sm text-red-400">
              {{ form.errors.address }}
            </div>
          </div>
        </div>
      </div>

      <!-- Assignment (Strict Accountability - Single Owner Rule) -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Assignment
        </h2>
        <p class="text-xs text-white/50">
          Assign single owners for strict accountability. One fronter handles initial contact, one closer handles deal closure.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label :class="labelClass">Fronter (Sales Lead)</label>
            <select
              v-model="form.fronter_id"
              :class="inputClass"
            >
              <option :value="null">— Select fronter —</option>
              <option
                v-for="user in props.users"
                :key="user.id"
                :value="user.id"
              >
                {{ user.name }}
              </option>
            </select>
            <div v-if="form.errors.fronter_id" class="mt-1 text-sm text-red-400">
              {{ form.errors.fronter_id }}
            </div>
            <div class="mt-1 text-xs text-white/40">
              Responsible for initial contact
            </div>
          </div>

          <div>
            <label :class="labelClass">Closer (Sales Closer)</label>
            <select
              v-model="form.closer_id"
              :class="inputClass"
            >
              <option :value="null">— Select closer —</option>
              <option
                v-for="user in props.users"
                :key="user.id"
                :value="user.id"
              >
                {{ user.name }}
              </option>
            </select>
            <div v-if="form.errors.closer_id" class="mt-1 text-sm text-red-400">
              {{ form.errors.closer_id }}
            </div>
            <div class="mt-1 text-xs text-white/40">
              Responsible for closing deal
            </div>
          </div>

          <div>
            <label :class="labelClass">Account Manager</label>
            <select
              v-model="form.assigned_account_manager_id"
              :class="inputClass"
            >
              <option :value="null">— Select account manager —</option>
              <option
                v-for="user in props.users"
                :key="user.id"
                :value="user.id"
              >
                {{ user.name }}
              </option>
            </select>
            <div
              v-if="form.errors.assigned_account_manager_id"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.assigned_account_manager_id }}
            </div>
            <div class="mt-1 text-xs text-white/40">
              Ongoing relationship manager
            </div>
          </div>
        </div>
      </div>

      <!-- Status & GBP -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Status & GBP
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label :class="labelClass">Client activation status</label>
            <select
              v-model="form.client_activation_status"
              :class="inputClass"
            >
              <option
                v-for="opt in activationOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <div
              v-if="form.errors.client_activation_status"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.client_activation_status }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Pipeline status</label>
            <select v-model="form.status" :class="inputClass">
              <option
                v-for="opt in statusOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <div v-if="form.errors.status" class="mt-1 text-sm text-red-400">
              {{ form.errors.status }}
            </div>
          </div>

          <div>
            <label :class="labelClass">GBP status</label>
            <select
              v-model="form.gbp_status"
              :class="inputClass"
            >
              <option
                v-for="opt in gbpStatusOptions"
                :key="opt.value"
                :value="opt.value || null"
              >
                {{ opt.label }}
              </option>
            </select>
            <div
              v-if="form.errors.gbp_status"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.gbp_status }}
            </div>
          </div>

          <div>
            <label :class="labelClass">GBP access status</label>
            <select
              v-model="form.gbp_access"
              :class="inputClass"
            >
              <option
                v-for="opt in gbpAccessOptions"
                :key="opt.value"
                :value="opt.value || null"
              >
                {{ opt.label }}
              </option>
            </select>
            <div
              v-if="form.errors.gbp_access"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.gbp_access }}
            </div>
          </div>
        </div>
      </div>

      <!-- Notes: read-only existing + append-only "Add note" -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Internal notes
        </h2>
        <p class="text-xs text-white/50">
          Existing notes are read-only. Use "Add note" to append a timestamped entry.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label :class="labelClass">Sales notes</label>
            <pre class="mb-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-white/70 whitespace-pre-wrap min-h-[4rem] max-h-32 overflow-y-auto">{{ client.notes_sales || 'No notes yet' }}</pre>
            <label :class="labelClass">Add note (append-only)</label>
            <textarea
              v-model="form.new_note_sales"
              rows="2"
              :class="textareaClass"
              placeholder="Add a note…"
            />
            <div v-if="form.errors.notes_sales" class="mt-1 text-sm text-red-400">{{ form.errors.notes_sales }}</div>
          </div>
          <div>
            <label :class="labelClass">CST notes</label>
            <pre class="mb-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-white/70 whitespace-pre-wrap min-h-[4rem] max-h-32 overflow-y-auto">{{ client.notes_cst || 'No notes yet' }}</pre>
            <label :class="labelClass">Add note (append-only)</label>
            <textarea
              v-model="form.new_note_cst"
              rows="2"
              :class="textareaClass"
              placeholder="Add a note…"
            />
            <div v-if="form.errors.notes_cst" class="mt-1 text-sm text-red-400">{{ form.errors.notes_cst }}</div>
          </div>
          <div>
            <label :class="labelClass">Tech notes</label>
            <pre class="mb-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-white/70 whitespace-pre-wrap min-h-[4rem] max-h-32 overflow-y-auto">{{ client.notes_tech || 'No notes yet' }}</pre>
            <label :class="labelClass">Add note (append-only)</label>
            <textarea
              v-model="form.new_note_tech"
              rows="2"
              :class="textareaClass"
              placeholder="Add a note…"
            />
            <div v-if="form.errors.notes_tech" class="mt-1 text-sm text-red-400">{{ form.errors.notes_tech }}</div>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="pt-4 flex justify-end">
        <button
          type="submit"
          class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Saving…' : 'Update client' }}
        </button>
      </div>
    </form>
  </PageShell>
</template>
