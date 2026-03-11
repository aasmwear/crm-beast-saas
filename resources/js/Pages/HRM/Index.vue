<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

type Department = {
  id: number
  name: string
  code: string
}

type Employee = {
  id: number
  name: string
  email: string
  designation?: string | null
  role: string
  department_id: number | null
  department_name: string | null
  status: string
  joined: string
}

type PageProps = {
  tenant?: { slug: string }
  organization?: { slug: string }
  flash?: { success?: string; error?: string }
}

const props = defineProps<{
  employees: Employee[]
  departments: Department[]
  roles: string[]
  organization?: { id: number; name: string; slug: string }
  organizationSlug?: string
  canCreate?: boolean
  canEdit?: boolean
  canDelete?: boolean
}>()

const page = usePage<PageProps>()

const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, unknown> = {}, absolute = false, config?: unknown) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

const org = computed(() => {
  return props.organization?.slug ?? props.organizationSlug ?? page.props.tenant?.slug ?? page.props.organization?.slug ?? 'acme'
})

const searchQuery = ref('')
const filteredEmployees = computed(() => {
  if (!searchQuery.value) return props.employees
  const query = searchQuery.value.toLowerCase()
  return props.employees.filter(
    (emp) =>
      emp.name.toLowerCase().includes(query) ||
      emp.email.toLowerCase().includes(query) ||
      emp.role.toLowerCase().includes(query) ||
      (emp.department_name && emp.department_name.toLowerCase().includes(query))
  )
})

function statusClass(status: string): string {
  if (status === 'Active') {
    return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40'
  }
  return 'bg-zinc-500/15 text-zinc-300 border border-zinc-500/40'
}

function roleClass(role: string): string {
  if (role === 'Admin') {
    return 'bg-violet-500/15 text-violet-300 border border-violet-500/40'
  }
  return 'bg-zinc-500/15 text-zinc-400 border border-zinc-500/40'
}

const showAddModal = ref(false)

function openAddModal() {
  addForm.reset()
  addForm.role = (props.roles && props.roles.length) ? props.roles[0] : ''
  showAddModal.value = true
}

function closeAddModal() {
  showAddModal.value = false
}

const addForm = useForm({
  name: '',
  email: '',
  job_title: '',
  joining_date: '' as string,
  role: '' as string,
  department_id: null as number | null,
})

function submitAdd() {
  addForm.post(r('hrm.store', { organization: org.value }), {
    preserveScroll: true,
    onSuccess: () => {
      closeAddModal()
    },
  })
}

const showEditModal = ref(false)
const editingEmployee = ref<Employee | null>(null)

const editForm = useForm({
  name: '',
  email: '',
  designation: '',
  department_id: null as number | null,
  role: '' as string,
})

function openEditModal(employee: Employee) {
  editingEmployee.value = employee
  editForm.name = employee.name
  editForm.email = employee.email
  editForm.designation = employee.designation ?? ''
  editForm.department_id = employee.department_id
  editForm.role = employee.role.split(',')[0]?.trim() || (props.roles?.[0] ?? '')
  editForm.clearErrors()
  showEditModal.value = true
}

function closeEditModal() {
  showEditModal.value = false
  editingEmployee.value = null
}

function submitEdit() {
  if (!editingEmployee.value) return
  editForm.put(r('hrm.update', { organization: org.value, user: editingEmployee.value.id }), {
    preserveScroll: true,
    onSuccess: () => closeEditModal(),
  })
}

function removeEmployee(employee: Employee) {
  if (!confirm(`Remove ${employee.name} from the organization?`)) return
  router.delete(r('hrm.destroy', { organization: org.value, user: employee.id }), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <section class="hero-slab">
      <div class="flex items-center justify-between gap-4">
        <div>
          <div class="text-xs text-white/60">
            Organization • {{ org }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Team & Employees
          </h1>
          <p class="mt-1 text-white/60">
            Manage your team members and their roles.
          </p>
        </div>

        <div v-if="props.canCreate !== false" class="flex items-center gap-2">
          <button
            type="button"
            @click="openAddModal"
            class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
          >
            <svg
              class="w-4 h-4 mr-2 inline-block"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
              />
            </svg>
            Add Employee
          </button>
        </div>
      </div>
    </section>

    <!-- Flash -->
    <div
      v-if="page.props.flash?.success"
      class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300"
    >
      {{ page.props.flash.success }}
    </div>
    <div
      v-if="page.props.flash?.error"
      class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
    >
      {{ page.props.flash.error }}
    </div>

    <!-- Search & Stats -->
    <div class="card-neo p-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="text-sm text-white/60">
            <span class="text-2xl font-bold text-white">{{ employees.length }}</span>
            <span class="ml-2">Total Members</span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="relative">
            <svg
              class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/40"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
              />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search employees..."
              class="w-full md:w-64 rounded-xl border border-white/10 bg-white/5 pl-10 pr-4 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Employee List -->
    <div class="card-neo overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wide text-white/60">
            <tr>
              <th class="px-6 py-4">Identity</th>
              <th class="px-6 py-4">Role</th>
              <th class="px-6 py-4">Department</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4">Joined</th>
              <th v-if="props.canEdit !== false || props.canDelete !== false" class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr
              v-for="employee in filteredEmployees"
              :key="employee.id"
              class="hover:bg-white/[0.02] transition"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--primary)] to-purple-600 text-sm font-semibold text-white"
                  >
                    {{ employee.name.charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <div class="font-medium text-white truncate">
                      {{ employee.name }}
                    </div>
                    <div class="text-xs text-white/60 truncate">
                      {{ employee.email }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                  :class="roleClass(employee.role)"
                >
                  {{ employee.role }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span
                  v-if="employee.department_name"
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-white/10 text-white/80 border border-white/10"
                >
                  {{ employee.department_name }}
                </span>
                <span v-else class="text-white/40 text-xs">—</span>
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                  :class="statusClass(employee.status)"
                >
                  <span
                    v-if="employee.status === 'Active'"
                    class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                  />
                  {{ employee.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-white/60 text-xs">
                {{ employee.joined }}
              </td>
              <td v-if="props.canEdit !== false || props.canDelete !== false" class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="props.canEdit !== false"
                    type="button"
                    class="rounded p-1.5 text-white/50 hover:bg-white/10 hover:text-white transition"
                    title="Edit"
                    @click="openEditModal(employee)"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                      />
                    </svg>
                  </button>
                  <button
                    v-if="props.canDelete !== false"
                    type="button"
                    class="rounded p-1.5 text-white/50 hover:bg-red-500/20 hover:text-red-300 transition"
                    title="Remove from organization"
                    @click="removeEmployee(employee)"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                      />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!filteredEmployees.length">
              <td :colspan="(props.canEdit !== false || props.canDelete !== false) ? 6 : 5" class="px-6 py-12 text-center text-sm text-white/50">
                <svg
                  class="mx-auto h-12 w-12 text-white/20 mb-3"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                  />
                </svg>
                <p>No employees found.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Employee Modal -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      @click.self="closeAddModal"
    >
      <div class="w-full max-w-md rounded-2xl border border-white/15 bg-[#0b0b0f] p-6 shadow-2xl">
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h2 class="text-xl font-semibold text-white">Add Employee</h2>
            <p class="mt-1 text-sm text-white/60">
              Create a new team member. They can sign in with the email and default password.
            </p>
          </div>
          <button
            type="button"
            @click="closeAddModal"
            class="text-white/40 hover:text-white transition"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="submitAdd" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">
              Full Name
            </label>
            <input
              v-model="addForm.name"
              type="text"
              required
              placeholder="Jane Doe"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
              :class="{ 'border-red-500/50': addForm.errors.name }"
            />
            <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-400">
              {{ addForm.errors.name }}
            </p>
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">
              Email Address
            </label>
            <input
              v-model="addForm.email"
              type="email"
              required
              placeholder="jane@example.com"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
              :class="{ 'border-red-500/50': addForm.errors.email }"
            />
            <p v-if="addForm.errors.email" class="mt-1 text-xs text-red-400">
              {{ addForm.errors.email }}
            </p>
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">
              Job Title
            </label>
            <input
              v-model="addForm.job_title"
              type="text"
              placeholder="e.g. Project Manager"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">
              Joining Date
            </label>
            <input
              v-model="addForm.joining_date"
              type="date"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">
              Role
            </label>
            <select
              v-model="addForm.role"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            >
              <option
                v-for="roleName in roles"
                :key="roleName"
                :value="roleName"
              >
                {{ roleName }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">
              Department
            </label>
            <select
              v-model="addForm.department_id"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            >
              <option :value="null">— None —</option>
              <option
                v-for="dept in departments"
                :key="dept.id"
                :value="dept.id"
              >
                {{ dept.name }} ({{ dept.code }})
              </option>
            </select>
            <p v-if="addForm.errors.department_id" class="mt-1 text-xs text-red-400">
              {{ addForm.errors.department_id }}
            </p>
          </div>

          <div class="pt-4 flex justify-end gap-2">
            <button
              type="button"
              @click="closeAddModal"
              class="btn-capsule text-white/60 hover:text-white"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90 disabled:opacity-50"
              :disabled="addForm.processing"
            >
              {{ addForm.processing ? 'Creating…' : 'Create Employee' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Employee Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      @click.self="closeEditModal"
    >
      <div class="w-full max-w-md rounded-2xl border border-white/15 bg-[#0b0b0f] p-6 shadow-2xl">
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h2 class="text-xl font-semibold text-white">Edit Employee</h2>
            <p class="mt-1 text-sm text-white/60">
              Update profile and role for this team member.
            </p>
          </div>
          <button
            type="button"
            @click="closeEditModal"
            class="text-white/40 hover:text-white transition"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="submitEdit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">Full Name</label>
            <input
              v-model="editForm.name"
              type="text"
              required
              placeholder="Jane Doe"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
              :class="{ 'border-red-500/50': editForm.errors.name }"
            />
            <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-400">{{ editForm.errors.name }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">Email Address</label>
            <input
              v-model="editForm.email"
              type="email"
              required
              placeholder="jane@example.com"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
              :class="{ 'border-red-500/50': editForm.errors.email }"
            />
            <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-400">{{ editForm.errors.email }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">Job Title</label>
            <input
              v-model="editForm.designation"
              type="text"
              placeholder="e.g. Project Manager"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">Role</label>
            <select
              v-model="editForm.role"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            >
              <option
                v-for="roleName in roles"
                :key="roleName"
                :value="roleName"
              >
                {{ roleName }}
              </option>
            </select>
            <p v-if="editForm.errors.role" class="mt-1 text-xs text-red-400">{{ editForm.errors.role }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-white/60 tracking-wide mb-1">Department</label>
            <select
              v-model="editForm.department_id"
              class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition"
            >
              <option :value="null">— None —</option>
              <option
                v-for="dept in departments"
                :key="dept.id"
                :value="dept.id"
              >
                {{ dept.name }} ({{ dept.code }})
              </option>
            </select>
            <p v-if="editForm.errors.department_id" class="mt-1 text-xs text-red-400">{{ editForm.errors.department_id }}</p>
          </div>

          <div class="pt-4 flex justify-end gap-2">
            <button
              type="button"
              @click="closeEditModal"
              class="btn-capsule text-white/60 hover:text-white"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90 disabled:opacity-50"
              :disabled="editForm.processing"
            >
              {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
