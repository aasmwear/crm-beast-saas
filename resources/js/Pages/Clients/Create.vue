<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

// ---------- Types ----------
type UserBrief = {
  id: number
  name: string
}

type ClientForm = {
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

  google_business_profile_status: string | null
  google_business_profile_access_status: string | null

  client_activation_status: string | null
  status: string | null

  notes_by_sales: string | null
  notes_by_cst: string | null
  notes_by_tech: string | null
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
  users: UserBrief[]
}>()

const org = computed(() => {
  if (props.organizationSlug) return props.organizationSlug
  const p = page.props
  return p.tenant?.slug ?? p.organization?.slug ?? 'acme'
})

// ---------- Form ----------
const form = useForm<ClientForm>({
  company_name: '',
  industry: null,
  niche: null,
  website: null,
  primary_contact_name: null,
  primary_contact_email: null,
  primary_contact_phone: null,
  address: null,

  fronter_id: null,  // Single user ID (strict accountability)
  closer_id: null,   // Single user ID (strict accountability)
  assigned_account_manager_id: null,

  google_business_profile_status: null,
  google_business_profile_access_status: null,

  client_activation_status: 'lead',
  status: 'lead',

  notes_by_sales: null,
  notes_by_cst: null,
  notes_by_tech: null,
})

const submit = () => {
  form.post(r('clients.store', { organization: org.value }))
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
  { value: 'verified', label: 'Verified' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'in_progress', label: 'In Progress' },
]

const gbpAccessOptions = [
  { value: '', label: '— Select access level —' },
  { value: 'none', label: 'No Access' },
  { value: 'pending', label: 'Access Pending' },
  { value: 'manager', label: 'Manager Access' },
  { value: 'owner', label: 'Owner Access' },
]
</script>

<template>
  <div class="space-y-6">
    <!-- Hero -->
    <section class="hero-slab">
      <div class="flex items-center justify-between gap-4">
        <div>
          <div class="text-xs text-white/60">
            Organization • {{ org.toUpperCase() }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Create Client
          </h1>
          <p class="mt-1 text-white/60">
            Enter the details for a new client record.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Link
            :href="r('clients.index', { organization: org })"
            class="btn-capsule text-xs"
          >
            Back to Clients
          </Link>
        </div>
      </div>
    </section>

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
              placeholder="Acme Roofing Co."
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
            <input
              v-model="form.website"
              :class="inputClass"
              placeholder="https://example.com"
            />
            <div
              v-if="form.errors.website"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.website }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Industry</label>
            <input
              v-model="form.industry"
              :class="inputClass"
              placeholder="Roofing / HVAC / Plumbing"
            />
            <div
              v-if="form.errors.industry"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.industry }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Niche</label>
            <input
              v-model="form.niche"
              :class="inputClass"
              placeholder="Residential Roofing / Commercial HVAC"
            />
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
            <input
              v-model="form.primary_contact_name"
              :class="inputClass"
              placeholder="John Doe"
            />
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
              placeholder="owner@example.com"
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
            <input
              v-model="form.primary_contact_phone"
              :class="inputClass"
              placeholder="+1 (555) 123-4567"
            />
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
              placeholder="Street, city, state, ZIP"
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
              v-model="form.google_business_profile_status"
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
              v-if="form.errors.google_business_profile_status"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.google_business_profile_status }}
            </div>
          </div>

          <div>
            <label :class="labelClass">GBP access status</label>
            <select
              v-model="form.google_business_profile_access_status"
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
              v-if="form.errors.google_business_profile_access_status"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.google_business_profile_access_status }}
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Internal notes
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label :class="labelClass">Sales notes</label>
            <textarea
              v-model="form.notes_by_sales"
              rows="4"
              :class="textareaClass"
            ></textarea>
            <div
              v-if="form.errors.notes_by_sales"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.notes_by_sales }}
            </div>
          </div>
          <div>
            <label :class="labelClass">CST notes</label>
            <textarea
              v-model="form.notes_by_cst"
              rows="4"
              :class="textareaClass"
            ></textarea>
            <div
              v-if="form.errors.notes_by_cst"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.notes_by_cst }}
            </div>
          </div>
          <div>
            <label :class="labelClass">Tech notes</label>
            <textarea
              v-model="form.notes_by_tech"
              rows="4"
              :class="textareaClass"
            ></textarea>
            <div
              v-if="form.errors.notes_by_tech"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.notes_by_tech }}
            </div>
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
          {{ form.processing ? 'Saving…' : 'Create client' }}
        </button>
      </div>
    </form>
  </div>
</template>
