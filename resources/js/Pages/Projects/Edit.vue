<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

defineOptions({ layout: AuthenticatedLayout })

// ---------- Types ----------
type ClientBrief = {
  id: number
  company_name: string
}

type UserBrief = {
  id: number
  name: string
}

type ProjectResource = {
  id: number
  client_id: number | null
  title: string
  project_code: string | null
  project_manager_id: number | null
  budget: number | string | null
  price: number | string | null
  currency: string | null
  billable: boolean
  status: string | null
}

type ProjectForm = ProjectResource

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
  project: ProjectResource
  clients: ClientBrief[]
  cstManagers: UserBrief[]
}>()

const project = props.project

const org = computed(() => {
  const p = page.props
  return p.tenant?.slug ?? p.organization?.slug ?? 'acme'
})

// ---------- Form ----------
const form = useForm<ProjectForm>({
  id: project.id,
  client_id: project.client_id ?? null,
  title: project.title ?? '',
  project_code: project.project_code ?? null,
  project_manager_id: project.project_manager_id ?? null,
  budget: project.budget ?? null,
  price: project.price ?? null,
  currency: project.currency ?? 'USD',
  billable: project.billable ?? false,
  status: project.status ?? 'Active',
})

const submit = () => {
  form.put(
    r('projects.update', {
      organization: org.value,
      project: project.id,
    }),
  )
}

// ---------- UI helpers ----------
const inputClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'

const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1'

const statusOptions = [
  { value: 'Planned', label: 'Planned' },
  { value: 'Active', label: 'Active' },
  { value: 'In Progress', label: 'In Progress' },
  { value: 'Blocked', label: 'Blocked' },
  { value: 'Completed', label: 'Completed' },
  { value: 'On Hold', label: 'On Hold' },
  { value: 'Cancelled', label: 'Cancelled' },
]
</script>

<template>
  <PageShell
    :header="{
      breadcrumb: `Organization • ${org.toUpperCase()}`,
      title: 'Edit Project',
      subtitle: `Update details for ${project.title ?? 'this project'}.`,
    }"
  >
    <template #header-actions>
      <Link
        :href="r('projects.index', { organization: org })"
        class="btn-capsule text-xs"
      >
        Back to Projects
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
            <label :class="labelClass">Project title *</label>
            <input
              v-model="form.title"
              :class="inputClass"
              required
            />
            <div
              v-if="form.errors.title"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.title }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Project code</label>
            <input
              v-model="form.project_code"
              :class="inputClass"
            />
            <div
              v-if="form.errors.project_code"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.project_code }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Client</label>
            <select
              v-model="form.client_id"
              :class="inputClass"
            >
              <option :value="null">— Select client —</option>
              <option
                v-for="client in props.clients"
                :key="client.id"
                :value="client.id"
              >
                {{ client.company_name }}
              </option>
            </select>
            <div
              v-if="form.errors.client_id"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.client_id }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Project Manager</label>
            <select
              v-model="form.project_manager_id"
              :class="inputClass"
            >
              <option :value="null">— Select project manager —</option>
              <option
                v-for="user in props.cstManagers"
                :key="user.id"
                :value="user.id"
              >
                {{ user.name }}
              </option>
            </select>
            <div
              v-if="form.errors.project_manager_id"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.project_manager_id }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Status *</label>
            <select
              v-model="form.status"
              :class="inputClass"
              required
            >
              <option
                v-for="opt in statusOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <div
              v-if="form.errors.status"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.status }}
            </div>
          </div>
        </div>
      </div>

      <!-- Financials -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Financials
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label :class="labelClass">Budget</label>
            <input
              v-model="form.budget"
              :class="inputClass"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
            />
            <div
              v-if="form.errors.budget"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.budget }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Price (billable amount)</label>
            <input
              v-model="form.price"
              :class="inputClass"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
            />
            <div
              v-if="form.errors.price"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.price }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Currency</label>
            <select
              v-model="form.currency"
              :class="inputClass"
            >
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
            </select>
          </div>

          <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-white/70 cursor-pointer">
              <input
                v-model="form.billable"
                type="checkbox"
                class="h-4 w-4 rounded border-white/20 bg-white/5 text-[var(--primary)] focus:ring-[var(--primary)]/60"
              />
              <span>Billable project</span>
            </label>
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
          {{ form.processing ? 'Saving…' : 'Update project' }}
        </button>
      </div>
    </form>
  </PageShell>
</template>
