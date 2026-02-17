<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface Role {
  id: number
  name: string
  is_team_scoped: boolean
  can_assign?: boolean
}

interface User {
  id: number
  name: string
  email: string
  is_super_admin: boolean
  active_organization_id: number
  created_at: string
  roles: Role[]
  can_update: boolean
  can_assign_roles: boolean
  can_delete: boolean
}

interface Organization {
  id: number
  name: string
  slug: string
}

const props = defineProps<{
  organization: Organization
  users: User[]
  availableRoles: Role[]
}>()

const page = usePage()

// Resolve org slug
const org = computed(() => props.organization.slug)

// Ziggy route helper
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  (window as any).route ? (window as any).route(name, params, absolute, config) : '#'

// Role management modal state
const showRoleModal = ref(false)
const selectedUser = ref<User | null>(null)
const selectedRoleIds = ref<Set<number>>(new Set())

// Search and filter
const searchQuery = ref('')

// Filtered users
const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) {
    return props.users
  }
  
  const query = searchQuery.value.toLowerCase()
  return props.users.filter(user => 
    user.name.toLowerCase().includes(query) || 
    user.email.toLowerCase().includes(query) ||
    user.roles.some(role => role.name.toLowerCase().includes(query))
  )
})

// Form for role updates
const form = useForm({
  role_ids: [] as number[]
})

// Open role management modal
function openRoleModal(user: User) {
  if (!user.can_assign_roles) {
    return
  }
  
  selectedUser.value = user
  selectedRoleIds.value = new Set(user.roles.map(r => r.id))
  showRoleModal.value = true
  form.clearErrors()
}

// Close role modal
function closeRoleModal() {
  showRoleModal.value = false
  selectedUser.value = null
  selectedRoleIds.value.clear()
  form.reset()
}

// Toggle role selection
function toggleRole(roleId: number) {
  if (selectedRoleIds.value.has(roleId)) {
    selectedRoleIds.value.delete(roleId)
  } else {
    selectedRoleIds.value.add(roleId)
  }
}

// Save role assignments
function saveRoles() {
  if (!selectedUser.value) return
  
  form.role_ids = Array.from(selectedRoleIds.value)
  
  form.put(r('users.update', { 
    organization: org.value, 
    user: selectedUser.value.id 
  }), {
    preserveScroll: true,
    onSuccess: () => {
      closeRoleModal()
      // Update the user's roles in the local state
      if (selectedUser.value) {
        const userIndex = props.users.findIndex(u => u.id === selectedUser.value!.id)
        if (userIndex !== -1) {
          const updatedRoles = props.availableRoles.filter(r => 
            form.role_ids.includes(r.id)
          )
          props.users[userIndex].roles = updatedRoles
        }
      }
    },
    onError: () => {
      // Errors will be displayed by the form
    }
  })
}

// Get role badge color
function getRoleBadgeClass(role: Role): string {
  const base = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '
  
  const roleName = role.name.toLowerCase()
  
  if (roleName.includes('admin') || roleName === 'company') {
    return base + 'bg-purple-900/50 text-purple-300'
  }
  
  if (roleName.includes('employee')) {
    return base + 'bg-blue-900/50 text-blue-300'
  }
  
  if (roleName.includes('client')) {
    return base + 'bg-green-900/50 text-green-300'
  }
  
  if (roleName === 'super-admin') {
    return base + 'bg-yellow-900/50 text-yellow-300'
  }
  
  return base + 'bg-gray-700/50 text-gray-300'
}

// Check if a role is currently assigned to the selected user
function isRoleAssigned(roleId: number): boolean {
  return selectedRoleIds.value.has(roleId)
}

// Filter assignable roles for the modal
const assignableRoles = computed(() => {
  return props.availableRoles.filter(role => role.can_assign !== false)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Hero Section -->
    <section class="hero-slab">
      <div class="flex items-end justify-between gap-6">
        <div>
          <div class="text-sm text-white/60">
            Organization • {{ org.toUpperCase() }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            User Management
          </h1>
          <p class="mt-1 text-white/60">
            Manage users and assign roles within your organization.
          </p>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <div class="glass-card p-6 space-y-6">
      <!-- Header with Search -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h3 class="text-lg font-semibold text-white">
            {{ filteredUsers.length }} User{{ filteredUsers.length !== 1 ? 's' : '' }}
          </h3>
          <p class="text-sm text-white/60 mt-1">
            Assign roles to control access and permissions
          </p>
        </div>

        <!-- Search -->
        <div class="relative max-w-md w-full">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search users..."
            class="w-full px-4 py-2.5 pl-10 rounded-lg bg-gray-800/50 border border-gray-700 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/40"
            viewBox="0 0 24 24"
            fill="none"
          >
            <path
              d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
            />
          </svg>
        </div>
      </div>

      <!-- Users Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-gray-700/50">
            <tr class="text-left text-sm text-white/60">
              <th class="pb-3 px-4 font-medium">Name</th>
              <th class="pb-3 px-4 font-medium">Email</th>
              <th class="pb-3 px-4 font-medium">Roles</th>
              <th class="pb-3 px-4 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700/30">
            <tr
              v-for="user in filteredUsers"
              :key="user.id"
              class="hover:bg-gray-800/20 transition"
            >
              <!-- Name -->
              <td class="py-4 px-4">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-indigo-600/20 flex items-center justify-center text-indigo-300 text-sm font-medium">
                    {{ user.name.slice(0, 2).toUpperCase() }}
                  </div>
                  <div>
                    <div class="text-white font-medium">{{ user.name }}</div>
                    <div
                      v-if="user.is_super_admin"
                      class="text-xs text-yellow-400"
                    >
                      Super Admin
                    </div>
                  </div>
                </div>
              </td>

              <!-- Email -->
              <td class="py-4 px-4">
                <div class="text-white/80">{{ user.email }}</div>
              </td>

              <!-- Roles -->
              <td class="py-4 px-4">
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="role in user.roles"
                    :key="role.id"
                    :class="getRoleBadgeClass(role)"
                  >
                    {{ role.name }}
                  </span>
                  <span
                    v-if="user.roles.length === 0"
                    class="text-white/40 text-sm"
                  >
                    No roles assigned
                  </span>
                </div>
              </td>

              <!-- Actions -->
              <td class="py-4 px-4 text-right">
                <button
                  v-if="user.can_assign_roles"
                  @click="openRoleModal(user)"
                  class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition"
                >
                  Manage Roles
                </button>
                <span
                  v-else
                  class="text-sm text-white/40"
                >
                  No access
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty State -->
        <div
          v-if="filteredUsers.length === 0"
          class="text-center py-12 text-white/50"
        >
          <p v-if="searchQuery">No users found matching "{{ searchQuery }}"</p>
          <p v-else>No users in this organization.</p>
        </div>
      </div>
    </div>

    <!-- Role Assignment Modal -->
    <teleport to="body">
      <div
        v-if="showRoleModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="closeRoleModal"
      >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" />

        <!-- Modal -->
        <div class="flex min-h-screen items-center justify-center p-4">
          <div
            class="relative bg-gray-900 rounded-2xl shadow-2xl border border-gray-700/50 max-w-2xl w-full overflow-hidden"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-700/50">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-semibold text-white">
                    Manage Roles
                  </h3>
                  <p class="text-sm text-white/60 mt-1">
                    {{ selectedUser?.name }} ({{ selectedUser?.email }})
                  </p>
                </div>
                <button
                  @click="closeRoleModal"
                  class="text-white/60 hover:text-white transition"
                >
                  <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none">
                    <path
                      d="M6 18L18 6M6 6l12 12"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                    />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-6 space-y-4 max-h-[60vh] overflow-y-auto">
              <div class="space-y-3">
                <label
                  v-for="role in assignableRoles"
                  :key="role.id"
                  class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-800/40 cursor-pointer transition border border-transparent"
                  :class="{ 'border-indigo-500 bg-gray-800/40': isRoleAssigned(role.id) }"
                >
                  <input
                    type="checkbox"
                    :checked="isRoleAssigned(role.id)"
                    @change="toggleRole(role.id)"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0"
                  />
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <span class="text-white font-medium capitalize">{{ role.name }}</span>
                      <span
                        v-if="role.is_team_scoped"
                        class="text-xs px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300"
                      >
                        Team
                      </span>
                      <span
                        v-else
                        class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300"
                      >
                        Global
                      </span>
                    </div>
                  </div>
                </label>

                <!-- No Roles Available -->
                <div
                  v-if="assignableRoles.length === 0"
                  class="text-center py-8 text-white/50"
                >
                  <p>No roles available to assign.</p>
                </div>
              </div>

              <!-- Validation Errors -->
              <div
                v-if="form.errors.role_ids"
                class="p-3 rounded-lg bg-red-900/20 border border-red-500/50 text-red-300 text-sm"
              >
                {{ form.errors.role_ids }}
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-700/50 flex items-center justify-end gap-3">
              <button
                @click="closeRoleModal"
                :disabled="form.processing"
                class="px-4 py-2 text-sm rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Cancel
              </button>
              <button
                @click="saveRoles"
                :disabled="form.processing || selectedRoleIds.size === 0"
                :class="[
                  'px-4 py-2 text-sm rounded-lg font-medium transition',
                  form.processing || selectedRoleIds.size === 0
                    ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                    : 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg'
                ]"
              >
                {{ form.processing ? 'Saving...' : 'Save Roles' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<style scoped>
.glass-card {
  background: rgba(17, 24, 39, 0.8);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
}

.hero-slab {
  padding: 2rem;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}
</style>
