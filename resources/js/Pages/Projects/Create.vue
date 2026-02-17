<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

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

type ProjectForm = {
  client_id: number | null
  title: string
  project_code: string | null
  project_manager_id: number | null
  budget: string | null
  price: string | null
  billable: boolean
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
  clients: ClientBrief[]
  cstManagers: UserBrief[]
}>()

const org = computed(() => {
  const p = page.props
  return p.tenant?.slug ?? p.organization?.slug ?? 'acme'
})

// ---------- Form ----------
const form = useForm<ProjectForm>({
  client_id: null,
  title: '',
  project_code: null,
  project_manager_id: null,
  budget: null,
  price: null,
  billable: false,
})

const submit = () => {
  form.post(r('projects.store', { organization: org.value }))
}

// ---------- UI helpers ----------
const inputClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'

const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1'
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
            Create Project
          </h1>
          <p class="mt-1 text-white/60">
            Enter the details for a new project.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Link
            :href="r('projects.index', { organization: org })"
            class="btn-capsule text-xs"
          >
            Back to Projects
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
            <label :class="labelClass">Project title *</label>
            <input
              v-model="form.title"
              :class="inputClass"
              required
              placeholder="Q1 Marketing Campaign"
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
              placeholder="PROJ-2024-001"
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
        </div>
      </div>

      <!-- Financials -->
      <div class="border-t border-white/10 pt-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Financials
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label :class="labelClass">Budget</label>
            <input
              v-model="form.budget"
              :class="inputClass"
              type="number"
              step="0.01"
              placeholder="10000.00"
            />
            <div
              v-if="form.errors.budget"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.budget }}
            </div>
          </div>

          <div>
            <label :class="labelClass">Price</label>
            <input
              v-model="form.price"
              :class="inputClass"
              type="number"
              step="0.01"
              placeholder="15000.00"
            />
            <div
              v-if="form.errors.price"
              class="mt-1 text-sm text-red-400"
            >
              {{ form.errors.price }}
            </div>
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
          {{ form.processing ? 'Saving…' : 'Create project' }}
        </button>
      </div>
    </form>
  </div>
</template>
