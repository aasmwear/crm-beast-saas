<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface Permission {
  id: number
  name: string
  module: string
}

interface Role {
  id: number
  name: string
  team_id: number | null
  is_team_scoped: boolean
  permission_ids: number[]
}

const props = defineProps<{
  roles: Role[]
  permissions: Permission[]
  groupedPermissions: Record<string, Permission[]>
}>()

const page = usePage()

// Resolve org slug
const org = computed(() => {
  const routeGlobal = (window as any).route
  const params = routeGlobal?.()?.params ?? {}
  if (params.organization) return params.organization
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

// Ziggy route helper
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  (window as any).route ? (window as any).route(name, params, absolute, config) : '#'

// Form state
const selectedRoleId = ref<number | null>(props.roles[0]?.id ?? null)
const searchQuery = ref('')
const expandedModules = ref<Set<string>>(new Set())

// Selected permissions (synced with selected role)
const selectedPermissionIds = ref<Set<number>>(new Set())

// Computed: currently selected role
const selectedRole = computed(() => 
  props.roles.find(role => role.id === selectedRoleId.value)
)

// Watch for role changes and sync permissions
watch(selectedRoleId, (newRoleId) => {
  const role = props.roles.find(r => r.id === newRoleId)
  if (role) {
    selectedPermissionIds.value = new Set(role.permission_ids)
    // Expand all modules by default when switching roles
    expandedModules.value = new Set(Object.keys(props.groupedPermissions))
  }
}, { immediate: true })

// Filtered permissions by search
const filteredGroupedPermissions = computed(() => {
  if (!searchQuery.value.trim()) {
    return props.groupedPermissions
  }
  
  const query = searchQuery.value.toLowerCase()
  const filtered: Record<string, Permission[]> = {}
  
  Object.entries(props.groupedPermissions).forEach(([module, perms]) => {
    const matchingPerms = perms.filter(p => 
      p.name.toLowerCase().includes(query) || 
      module.toLowerCase().includes(query)
    )
    if (matchingPerms.length > 0) {
      filtered[module] = matchingPerms
    }
  })
  
  return filtered
})

// Module statistics
function getModuleStats(module: string) {
  const perms = props.groupedPermissions[module] || []
  const selected = perms.filter(p => selectedPermissionIds.value.has(p.id)).length
  return { total: perms.length, selected }
}

// Toggle module expansion
function toggleModule(module: string) {
  if (expandedModules.value.has(module)) {
    expandedModules.value.delete(module)
  } else {
    expandedModules.value.add(module)
  }
}

// Toggle single permission
function togglePermission(permId: number) {
  if (selectedPermissionIds.value.has(permId)) {
    selectedPermissionIds.value.delete(permId)
  } else {
    selectedPermissionIds.value.add(permId)
  }
}

// Toggle all permissions in a module
function toggleModulePermissions(module: string, selectAll: boolean) {
  const perms = props.groupedPermissions[module] || []
  perms.forEach(p => {
    if (selectAll) {
      selectedPermissionIds.value.add(p.id)
    } else {
      selectedPermissionIds.value.delete(p.id)
    }
  })
}

// Check if all permissions in a module are selected
function areAllModulePermissionsSelected(module: string): boolean {
  const perms = props.groupedPermissions[module] || []
  return perms.length > 0 && perms.every(p => selectedPermissionIds.value.has(p.id))
}

// Expand/collapse all modules
function expandAll() {
  expandedModules.value = new Set(Object.keys(props.groupedPermissions))
}

function collapseAll() {
  expandedModules.value.clear()
}

// Save form
const form = useForm({
  role_id: null as number | null,
  permission_ids: [] as number[]
})

function savePermissions() {
  if (!selectedRoleId.value) return
  
  form.role_id = selectedRoleId.value
  form.permission_ids = Array.from(selectedPermissionIds.value)
  
  form.post(r('roles.save', { organization: org.value }), {
    preserveScroll: true,
    onSuccess: () => {
      // Update the role's permission_ids in props
      const role = props.roles.find(r => r.id === selectedRoleId.value)
      if (role) {
        role.permission_ids = form.permission_ids
      }
    }
  })
}

// Module priority order (as per requirements)
const modulePriorityOrder = [
  'clients',
  'projects',
  'tasks',
  'attendance',
  'announcements',
  'notifications',
  'audit-log',
  'settings',
  'billing',
  'users',
  'organizations',
  'departments',
  'reports',
  'roles',
  'permissions',
  'subscriptions',
  'portal',
]

// Sort modules by priority
const sortedModules = computed(() => {
  const modules = Object.keys(filteredGroupedPermissions.value)
  return modules.sort((a, b) => {
    const indexA = modulePriorityOrder.indexOf(a)
    const indexB = modulePriorityOrder.indexOf(b)
    
    // If both are in the priority list, sort by priority
    if (indexA !== -1 && indexB !== -1) return indexA - indexB
    
    // If only one is in the priority list, it comes first
    if (indexA !== -1) return -1
    if (indexB !== -1) return 1
    
    // Otherwise, sort alphabetically
    return a.localeCompare(b)
  })
})

// Module icons (optional, can be extended)
const moduleIcons: Record<string, string> = {
  clients: '👥',
  projects: '📁',
  tasks: '✅',
  attendance: '⏰',
  announcements: '📢',
  notifications: '🔔',
  'audit-log': '📋',
  settings: '⚙️',
  billing: '💳',
  users: '👤',
  organizations: '🏢',
  departments: '🏬',
  reports: '📊',
  roles: '🎭',
  permissions: '🔐',
  subscriptions: '💎',
  portal: '🌐',
}
</script>

<template>
  <div class="space-y-6">
    <!-- Hero Section -->
    <section class="hero-slab">
      <div class="flex items-end justify-between gap-6">
        <div>
          <div class="text-sm text-white/60">
            Settings • {{ org.toUpperCase() }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Role & Permission Editor
          </h1>
          <p class="mt-1 text-white/60">
            Manage granular permissions for each role in your organization.
          </p>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <div class="grid grid-cols-12 gap-6">
      <!-- Left Panel: Role List -->
      <div class="col-span-12 lg:col-span-3">
        <div class="glass-card p-4 space-y-3">
          <h3 class="text-sm font-semibold text-white/80 mb-3">Select Role</h3>
          <div class="space-y-2">
            <button
              v-for="role in roles"
              :key="role.id"
              @click="selectedRoleId = role.id"
              :class="[
                'w-full text-left px-3 py-2.5 rounded-lg transition-all',
                selectedRoleId === role.id
                  ? 'bg-indigo-600 text-white shadow-lg'
                  : 'bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white'
              ]"
            >
              <div class="flex items-center justify-between">
                <span class="font-medium capitalize">{{ role.name }}</span>
                <span
                  v-if="role.is_team_scoped"
                  class="text-xs px-2 py-0.5 rounded-full bg-white/10"
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
            </button>
          </div>
        </div>
      </div>

      <!-- Right Panel: Permission Editor -->
      <div class="col-span-12 lg:col-span-9">
        <div class="glass-card p-6 space-y-6">
          <!-- Header with Search and Actions -->
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-white">
                Permissions for: <span class="text-indigo-400 capitalize">{{ selectedRole?.name }}</span>
              </h3>
              <p class="text-sm text-white/60 mt-1">
                {{ Array.from(selectedPermissionIds).length }} / {{ permissions.length }} permissions selected
              </p>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="expandAll"
                class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white transition"
              >
                Expand All
              </button>
              <button
                @click="collapseAll"
                class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white transition"
              >
                Collapse All
              </button>
              <button
                @click="savePermissions"
                :disabled="form.processing"
                :class="[
                  'px-4 py-1.5 text-sm rounded-lg font-medium transition',
                  form.processing
                    ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                    : 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg'
                ]"
              >
                {{ form.processing ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </div>

          <!-- Search -->
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search permissions..."
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

          <!-- Permission Modules -->
          <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2">
            <div
              v-for="module in sortedModules"
              :key="module"
              class="border border-gray-700/50 rounded-lg bg-gray-900/30 overflow-hidden"
            >
              <!-- Module Header -->
              <div
                @click="toggleModule(module)"
                class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-800/30 transition"
              >
                <div class="flex items-center gap-3">
                  <span class="text-xl">{{ moduleIcons[module] || '📦' }}</span>
                  <div>
                    <h4 class="text-sm font-semibold text-white capitalize">
                      {{ module }}
                    </h4>
                    <p class="text-xs text-white/50">
                      {{ getModuleStats(module).selected }} / {{ getModuleStats(module).total }} selected
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <button
                    @click.stop="toggleModulePermissions(module, !areAllModulePermissionsSelected(module))"
                    class="px-2 py-1 text-xs rounded bg-gray-800 text-white/70 hover:text-white transition"
                  >
                    {{ areAllModulePermissionsSelected(module) ? 'Deselect All' : 'Select All' }}
                  </button>
                  <svg
                    :class="[
                      'w-5 h-5 text-white/60 transition-transform',
                      expandedModules.has(module) ? 'rotate-180' : ''
                    ]"
                    viewBox="0 0 24 24"
                    fill="none"
                  >
                    <path
                      d="M6 9l6 6 6-6"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                    />
                  </svg>
                </div>
              </div>

              <!-- Module Permissions -->
              <div
                v-show="expandedModules.has(module)"
                class="p-4 pt-0 space-y-2"
              >
                <label
                  v-for="perm in filteredGroupedPermissions[module]"
                  :key="perm.id"
                  class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-800/40 cursor-pointer transition"
                >
                  <input
                    type="checkbox"
                    :checked="selectedPermissionIds.has(perm.id)"
                    @change="togglePermission(perm.id)"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0"
                  />
                  <span class="text-sm text-white/80 font-mono">{{ perm.name }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- No Results -->
          <div
            v-if="sortedModules.length === 0"
            class="text-center py-12 text-white/50"
          >
            <p>No permissions found matching "{{ searchQuery }}"</p>
          </div>
        </div>
      </div>
    </div>
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
