<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'
import PermissionMatrix from '@/Components/Permissions/PermissionMatrix.vue'
import type { PermissionCatalog, Permission } from '@/lib/permissionCatalog'
import { getRecognizedPermissionNames } from '@/lib/permissionCatalog'
import { toSlug, sanitizeSlug } from '@/lib/slug'

defineOptions({ layout: AuthenticatedLayout })

interface Role {
  id: number
  name: string
  team_id: number | null
  is_team_scoped: boolean
  is_editable?: boolean
  permission_ids: number[]
  permission_count: number
  users_count: number
}

const props = defineProps<{
  roles: Role[]
  permissions: Permission[]
  groupedPermissions: Record<string, Permission[]>
  permissionCatalog: PermissionCatalog
}>()

const page = usePage()
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string; created_role_id?: number } }).flash)

const org = computed(() => {
  const routeGlobal = (window as any).route
  const params = routeGlobal?.()?.params ?? {}
  if (params.organization) return params.organization
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

const r = (name: string, params: Record<string, string | number> = {}) =>
  (window as any).route ? (window as any).route(name, { ...params, organization: org.value }) : '#'

// Create Role modal
const showCreateModal = ref(false)
const createRoleForm = useForm<{ name: string }>({ name: '' })
const displayName = ref('')
const slugOverride = ref('')
const showSlugOverride = ref(false)

// Edit Role modal (team-scoped only)
const showEditModal = ref(false)
const editRoleForm = useForm<{ name: string }>({ name: '' })
const editDisplayName = ref('')
const editSlugOverride = ref('')
const editShowSlugOverride = ref(false)

// Delete Role confirmation
const showDeleteConfirm = ref(false)
const deleteForm = useForm({})

// Clone role (team-scoped only; direct POST, no modal)
const cloneForm = useForm({})

const slugFromDisplay = computed(() => toSlug(displayName.value))
const effectiveSlug = computed(() => {
  const manual = slugOverride.value.trim()
  if (manual) return sanitizeSlug(manual)
  return slugFromDisplay.value
})

function openCreateModal() {
  createRoleForm.reset()
  createRoleForm.clearErrors()
  displayName.value = ''
  slugOverride.value = ''
  showSlugOverride.value = false
  showCreateModal.value = true
}

function closeCreateModal() {
  showCreateModal.value = false
  createRoleForm.reset()
  displayName.value = ''
  slugOverride.value = ''
  showSlugOverride.value = false
}

function onSlugOverrideInput(e: Event) {
  const target = e.target as HTMLInputElement
  slugOverride.value = sanitizeSlug(target.value)
}

function submitCreateRole() {
  const slug = effectiveSlug.value
  if (!slug) {
    createRoleForm.setError('name', 'Enter a role name.')
    return
  }
  createRoleForm.name = slug
  createRoleForm.post(r('roles.store'), {
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
      // selectedRoleId will be set by watcher when flash.created_role_id arrives
    },
  })
}

const editSlugFromDisplay = computed(() => toSlug(editDisplayName.value))
const effectiveEditSlug = computed(() => {
  const manual = editSlugOverride.value.trim()
  if (manual) return sanitizeSlug(manual)
  return editSlugFromDisplay.value
})

function openEditModal() {
  const role = selectedRole.value
  if (!role?.is_team_scoped) return
  editDisplayName.value = role.name.replace(/-/g, ' ')
  editSlugOverride.value = ''
  editShowSlugOverride.value = false
  editRoleForm.reset()
  editRoleForm.clearErrors()
  showEditModal.value = true
}

function closeEditModal() {
  showEditModal.value = false
  editRoleForm.reset()
  editDisplayName.value = ''
  editSlugOverride.value = ''
  editShowSlugOverride.value = false
}

function submitEditRole() {
  const slug = effectiveEditSlug.value
  if (!slug) {
    editRoleForm.setError('name', 'Enter a role name.')
    return
  }
  const role = selectedRole.value
  if (!role) return
  editRoleForm.name = slug
  editRoleForm.patch(r('roles.updateRole', { role: role.id }), {
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
      role.name = slug
    },
  })
}

function openDeleteConfirm() {
  showDeleteConfirm.value = true
}

function closeDeleteConfirm() {
  showDeleteConfirm.value = false
}

function submitCloneRole() {
  const role = selectedRole.value
  if (!role?.is_team_scoped) return
  cloneForm.post(r('roles.clone', { role: role.id }), {
    preserveScroll: true,
    onSuccess: () => {
      // created_role_id in flash will trigger watcher to select new role
    },
  })
}

function submitDeleteRole() {
  const role = selectedRole.value
  if (!role || role.users_count > 0) return
  deleteForm.delete(r('roles.destroy', { role: role.id }), {
    preserveScroll: true,
    onSuccess: () => {
      closeDeleteConfirm()
      const idx = props.roles.findIndex(r => r.id === role.id)
      if (idx >= 0 && selectedRoleId.value === role.id) {
        const next = props.roles[idx - 1] ?? props.roles[idx + 1]
        selectedRoleId.value = next?.id ?? null
      }
    },
  })
}

const canEditRole = computed(() => {
  const role = selectedRole.value
  return role?.is_team_scoped ?? false
})

const canEditPermissions = computed(() => {
  const role = selectedRole.value
  return (role?.is_editable ?? role?.is_team_scoped ?? false)
})

const canDeleteRole = computed(() => {
  const role = selectedRole.value
  return role?.is_team_scoped && (role?.users_count ?? 0) === 0
})

// Mode: 'matrix' | 'advanced'
const editorMode = ref<'matrix' | 'advanced'>('matrix')

// Role selection
const selectedRoleId = ref<number | null>(props.roles[0]?.id ?? null)
const selectedRole = computed(() =>
  props.roles.find(role => role.id === selectedRoleId.value)
)

// Selected permissions (synced with selected role; user edits mutate this)
const selectedPermissionIds = ref<Set<number>>(new Set())

// Track "baseline" (last saved) to detect unsaved changes
const baselinePermissionIds = ref<Set<number>>(new Set())

const hasUnsavedChanges = computed(() => {
  if (!canEditPermissions.value) return false
  if (selectedPermissionIds.value.size !== baselinePermissionIds.value.size) return true
  for (const id of selectedPermissionIds.value) {
    if (!baselinePermissionIds.value.has(id)) return true
  }
  for (const id of baselinePermissionIds.value) {
    if (!selectedPermissionIds.value.has(id)) return true
  }
  return false
})

function syncFromRole() {
  const role = props.roles.find(r => r.id === selectedRoleId.value)
  if (role) {
    selectedPermissionIds.value = new Set(role.permission_ids)
    baselinePermissionIds.value = new Set(role.permission_ids)
  }
}

watch(selectedRoleId, () => syncFromRole(), { immediate: true })

// Auto-select newly created role when flash.created_role_id is set
watch(
  () => flash.value?.created_role_id,
  (id) => {
    if (id != null && props.roles.some(r => r.id === id)) {
      selectedRoleId.value = id
      syncFromRole()
    }
  },
  { immediate: true }
)

watch(
  () => props.roles,
  (roles) => {
    if (roles.length && selectedRoleId.value === null) {
      selectedRoleId.value = roles[0].id
    }
    syncFromRole()
  },
  { deep: true }
)

// Matrix: search, expanded modules
const matrixSearchQuery = ref('')
const expandedModules = ref<Set<string>>(new Set())

function toggleMatrixModule(moduleKey: string) {
  const next = new Set(expandedModules.value)
  if (next.has(moduleKey)) next.delete(moduleKey)
  else next.add(moduleKey)
  expandedModules.value = next
}

function getPermissionIdsForModule(moduleKey: string): number[] {
  const catalog = props.permissionCatalog
  if (!catalog) return []
  const names: string[] = []
  const matrix = catalog.matrix[moduleKey]
  if (matrix) {
    for (const p of Object.values(matrix)) names.push(p)
  }
  for (const s of catalog.specials) {
    if (s.module === moduleKey) names.push(s.permission)
  }
  const nameToId: Record<string, number> = {}
  for (const p of props.permissions) nameToId[p.name] = p.id
  return names.map(n => nameToId[n]).filter((id): id is number => id != null)
}

function toggleMatrixModuleAll(moduleKey: string, selectAll: boolean) {
  const ids = getPermissionIdsForModule(moduleKey)
  const next = new Set(selectedPermissionIds.value)
  ids.forEach(id => (selectAll ? next.add(id) : next.delete(id)))
  selectedPermissionIds.value = next
}

function expandAllMatrix() {
  expandedModules.value = new Set(
    (props.permissionCatalog?.modules ?? []).map(m => m.key)
  )
}

function collapseAllMatrix() {
  expandedModules.value = new Set()
}

function togglePermission(permId: number) {
  const next = new Set(selectedPermissionIds.value)
  if (next.has(permId)) next.delete(permId)
  else next.add(permId)
  selectedPermissionIds.value = next
}

// Advanced mode: filters
type AdvancedFilter = 'all' | 'recognized' | 'orphan'
const advancedFilter = ref<AdvancedFilter>('recognized')

const recognizedNames = computed(() =>
  props.permissionCatalog ? getRecognizedPermissionNames(props.permissionCatalog) : new Set<string>()
)

const filteredGroupedPermissions = computed(() => {
  let base = props.groupedPermissions
  if (advancedFilter.value === 'recognized') {
    const filtered: Record<string, Permission[]> = {}
    for (const [mod, perms] of Object.entries(base)) {
      const match = perms.filter(p => recognizedNames.value.has(p.name))
      if (match.length) filtered[mod] = match
    }
    base = filtered
  } else if (advancedFilter.value === 'orphan') {
    const filtered: Record<string, Permission[]> = {}
    for (const [mod, perms] of Object.entries(base)) {
      const match = perms.filter(p => !recognizedNames.value.has(p.name))
      if (match.length) filtered[mod] = match
    }
    base = filtered
  }
  const query = matrixSearchQuery.value.toLowerCase().trim()
  if (!query) return base
  const out: Record<string, Permission[]> = {}
  for (const [mod, perms] of Object.entries(base)) {
    const match = perms.filter(
      p =>
        p.name.toLowerCase().includes(query) || mod.toLowerCase().includes(query)
    )
    if (match.length) out[mod] = match
  }
  return out
})

const modulePriorityOrder = [
  'clients', 'projects', 'tasks', 'attendance', 'announcements', 'notifications',
  'audit-log', 'settings', 'billing', 'users', 'organizations', 'departments',
  'reports', 'roles', 'permissions', 'subscriptions', 'portal',
]

const sortedModules = computed(() => {
  const modules = Object.keys(filteredGroupedPermissions.value)
  return modules.sort((a, b) => {
    const iA = modulePriorityOrder.indexOf(a)
    const iB = modulePriorityOrder.indexOf(b)
    if (iA !== -1 && iB !== -1) return iA - iB
    if (iA !== -1) return -1
    if (iB !== -1) return 1
    return a.localeCompare(b)
  })
})

const moduleIcons: Record<string, string> = {
  clients: '👥', projects: '📁', tasks: '✅', attendance: '⏰',
  announcements: '📢', notifications: '🔔', 'audit-log': '📋', settings: '⚙️',
  billing: '💳', users: '👤', organizations: '🏢', departments: '🏬',
  reports: '📊', roles: '🎭', permissions: '🔐', subscriptions: '💎', portal: '🌐',
}

function getModuleStats(module: string) {
  const perms = filteredGroupedPermissions.value[module] || []
  const selected = perms.filter(p => selectedPermissionIds.value.has(p.id)).length
  return { total: perms.length, selected }
}

function toggleModule(module: string) {
  const next = new Set(expandedModules.value)
  if (next.has(module)) next.delete(module)
  else next.add(module)
  expandedModules.value = next
}

function toggleModulePermissions(module: string, selectAll: boolean) {
  const perms = filteredGroupedPermissions.value[module] || []
  const next = new Set(selectedPermissionIds.value)
  perms.forEach(p => {
    if (selectAll) next.add(p.id)
    else next.delete(p.id)
  })
  selectedPermissionIds.value = next
}

function areAllModulePermissionsSelected(module: string): boolean {
  const perms = filteredGroupedPermissions.value[module] || []
  return perms.length > 0 && perms.every(p => selectedPermissionIds.value.has(p.id))
}

function expandAll() {
  expandedModules.value = new Set(Object.keys(filteredGroupedPermissions.value))
}

function collapseAll() {
  expandedModules.value = new Set()
}

// Save / Discard
const saveForm = useForm({
  role_id: null as number | null,
  permission_ids: [] as number[],
})

function savePermissions() {
  if (!selectedRoleId.value) return
  const role = props.roles.find(r => r.id === selectedRoleId.value)
  if (role && !(role.is_editable ?? role.is_team_scoped)) return
  saveForm.role_id = selectedRoleId.value
  saveForm.permission_ids = Array.from(selectedPermissionIds.value)
  saveForm.post(r('roles.save'), {
    preserveScroll: true,
    onSuccess: () => {
      baselinePermissionIds.value = new Set(saveForm.permission_ids)
      const role = props.roles.find(r => r.id === selectedRoleId.value)
      if (role) role.permission_ids = [...saveForm.permission_ids]
    },
  })
}

function discardChanges() {
  syncFromRole()
}

// Leave-with-unsaved confirmation
const showLeaveConfirm = ref(false)
const pendingNavigation = ref<{ url: string } | null>(null)
const allowNextLeave = ref(false)

const removeBeforeListener = router.on('before', (event: { detail: { visit: { url: { href?: string } | string; method?: string } }; preventDefault: () => void }) => {
  if (allowNextLeave.value) return
  if (!hasUnsavedChanges.value) return
  if (!selectedRole.value?.is_editable) return
  // Do NOT block form submissions (Save, Create Role) — only block actual navigation (GET)
  const method = (event.detail.visit?.method ?? 'get').toLowerCase()
  if (method !== 'get') return
  const url = typeof event.detail.visit.url === 'string' ? event.detail.visit.url : event.detail.visit.url?.href
  if (!url) return
  event.preventDefault()
  pendingNavigation.value = { url }
  showLeaveConfirm.value = true
})

function confirmLeave() {
  allowNextLeave.value = true
  if (pendingNavigation.value) {
    router.visit(pendingNavigation.value.url)
    pendingNavigation.value = null
  }
  showLeaveConfirm.value = false
  setTimeout(() => { allowNextLeave.value = false }, 100)
}

function cancelLeave() {
  pendingNavigation.value = null
  showLeaveConfirm.value = false
}

function handleBeforeUnload(e: BeforeUnloadEvent) {
  if (hasUnsavedChanges.value && (selectedRole.value?.is_editable ?? selectedRole.value?.is_team_scoped)) e.preventDefault()
}

window.addEventListener('beforeunload', handleBeforeUnload)
onUnmounted(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
  removeBeforeListener?.()
})

// Catalog permission count (recognized only) for Matrix mode
const catalogPermissionCount = computed(() => {
  if (!props.permissionCatalog) return 0
  let total = 0
  for (const mod of Object.keys(props.permissionCatalog.matrix)) {
    total += Object.keys(props.permissionCatalog.matrix[mod]).length
  }
  total += props.permissionCatalog.specials.length
  return total
})

const catalogSelectedCount = computed(() => {
  if (!props.permissionCatalog) return 0
  const nameToId: Record<string, number> = {}
  for (const p of props.permissions) nameToId[p.name] = p.id
  let count = 0
  for (const mod of Object.keys(props.permissionCatalog.matrix)) {
    for (const perm of Object.values(props.permissionCatalog.matrix[mod])) {
      const id = nameToId[perm]
      if (id != null && selectedPermissionIds.value.has(id)) count++
    }
  }
  for (const s of props.permissionCatalog.specials) {
    const id = nameToId[s.permission]
    if (id != null && selectedPermissionIds.value.has(id)) count++
  }
  return count
})
</script>

<template>
  <PageShell
    v-model="editorMode"
    :sticky="true"
    :tabs="[
      { key: 'matrix', label: 'Quick Matrix' },
      { key: 'advanced', label: 'Advanced' },
    ]"
    :local="true"
    :header="{
      breadcrumb: `Settings • ${org}`,
      title: 'Roles & Permissions',
      subtitle: 'Manage granular permissions for each role in your organization.',
    }"
  >
    <template #header-actions>
      <button
        type="button"
        @click="openCreateModal"
        class="px-4 py-2 rounded-lg font-medium transition bg-white/10 text-white border border-white/20 hover:bg-white/20"
      >
        Create Role
      </button>
    </template>

    <div
      v-if="flash?.success"
      class="rounded-lg bg-green-900/30 border border-green-700/50 px-4 py-2 text-green-200 text-sm"
    >
      {{ flash.success }}
    </div>

    <div class="grid grid-cols-12 gap-6">
      <!-- Left: Role list -->
      <div class="col-span-12 lg:col-span-3">
        <div class="glass-card p-4 space-y-3">
          <h3 class="text-sm font-semibold text-white/80 mb-3">Select Role</h3>
          <div class="space-y-2">
            <button
              v-for="role in roles"
              :key="role.id"
              type="button"
              :class="[
                'w-full text-left px-3 py-2.5 rounded-lg transition-all',
                selectedRoleId === role.id
                  ? 'bg-indigo-600 text-white shadow-lg'
                  : 'bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white'
              ]"
              @click="selectedRoleId = role.id"
            >
              <div class="flex flex-col gap-0.5">
                <div class="flex items-center justify-between">
                  <span class="font-medium capitalize">{{ role.name.replace(/-/g, ' ') }}</span>
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
                <span class="text-xs text-white/50">{{ role.permission_count }} permissions</span>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Editor -->
      <div class="col-span-12 lg:col-span-9">
        <div class="glass-card p-6 space-y-6">
          <!-- Editor header (mode-specific actions) -->
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-white">
                Permissions for: <span class="text-indigo-400 capitalize">{{ selectedRole?.name?.replace(/-/g, ' ') }}</span>
              </h3>
              <div class="flex items-center gap-2 mt-1">
                <p class="text-sm text-white/60">
                <template v-if="editorMode === 'matrix'">
                  {{ catalogSelectedCount }} / {{ catalogPermissionCount }} catalog permissions
                </template>
                <template v-else>
                  {{ selectedRole ? Array.from(selectedPermissionIds).length : 0 }} / {{ permissions.length }} permissions
                </template>
                </p>
                <template v-if="canEditRole">
                  <button
                    type="button"
                    @click="openEditModal"
                    class="px-2 py-1 text-xs rounded bg-gray-800 text-white/70 hover:text-white transition"
                  >
                    Edit name
                  </button>
                  <button
                    type="button"
                    :disabled="cloneForm.processing"
                    @click="submitCloneRole"
                    class="px-2 py-1 text-xs rounded bg-gray-800 text-white/70 hover:text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {{ cloneForm.processing ? 'Cloning...' : 'Clone role' }}
                  </button>
                </template>
                <template v-if="canDeleteRole">
                  <button
                    type="button"
                    @click="openDeleteConfirm"
                    class="px-2 py-1 text-xs rounded bg-red-900/50 text-red-300 hover:bg-red-900 transition"
                  >
                    Delete role
                  </button>
                </template>
                <span v-else-if="selectedRole?.is_team_scoped && (selectedRole?.users_count ?? 0) > 0" class="text-xs text-amber-400">
                  (Cannot delete: assigned to {{ selectedRole.users_count }} user(s))
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <template v-if="editorMode === 'matrix'">
                <button
                  type="button"
                  @click="expandAllMatrix"
                  class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white transition"
                >
                  Expand All
                </button>
                <button
                  type="button"
                  @click="collapseAllMatrix"
                  class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white transition"
                >
                  Collapse All
                </button>
              </template>
              <template v-else>
                <select
                  v-model="advancedFilter"
                  class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 border border-gray-700 text-white focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="all">All DB Permissions</option>
                  <option value="recognized">Recognized Only</option>
                  <option value="orphan">Orphan Only</option>
                </select>
                <button
                  type="button"
                  @click="expandAll"
                  class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white transition"
                >
                  Expand All
                </button>
                <button
                  type="button"
                  @click="collapseAll"
                  class="px-3 py-1.5 text-sm rounded-lg bg-gray-800/50 text-white/70 hover:bg-gray-800 hover:text-white transition"
                >
                  Collapse All
                </button>
              </template>
            </div>
          </div>

          <!-- Search -->
          <div class="relative">
            <input
              v-model="matrixSearchQuery"
              type="text"
              :placeholder="editorMode === 'matrix' ? 'Search modules...' : 'Search permissions...'"
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

          <!-- MODE A: Quick Matrix -->
          <div v-if="editorMode === 'matrix'" class="max-h-[600px] overflow-y-auto pr-2">
            <PermissionMatrix
              v-if="permissionCatalog"
              :catalog="permissionCatalog"
              :permissions="permissions"
              :selected-permission-ids="selectedPermissionIds"
              :search-query="matrixSearchQuery"
              :expanded-modules="expandedModules"
              @toggle="togglePermission"
              @toggle-module="toggleMatrixModule"
              @toggle-module-all="toggleMatrixModuleAll"
            />
          </div>

          <!-- MODE B: Advanced (grouped list) -->
          <div v-else class="space-y-3 max-h-[600px] overflow-y-auto pr-2">
            <div
              v-for="module in sortedModules"
              :key="module"
              class="border border-gray-700/50 rounded-lg bg-gray-900/30 overflow-hidden"
            >
              <div
                class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-800/30 transition"
                @click="toggleModule(module)"
              >
                <div class="flex items-center gap-3">
                  <span class="text-xl">{{ moduleIcons[module] || '📦' }}</span>
                  <div>
                    <h4 class="text-sm font-semibold text-white capitalize">{{ module }}</h4>
                    <p class="text-xs text-white/50">
                      {{ getModuleStats(module).selected }} / {{ getModuleStats(module).total }} selected
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <button
                    type="button"
                    class="px-2 py-1 text-xs rounded bg-gray-800 text-white/70 hover:text-white transition"
                    @click.stop="toggleModulePermissions(module, !areAllModulePermissionsSelected(module))"
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
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  </svg>
                </div>
              </div>
              <div v-show="expandedModules.has(module)" class="p-4 pt-0 space-y-2">
                <label
                  v-for="perm in filteredGroupedPermissions[module]"
                  :key="perm.id"
                  class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-800/40 cursor-pointer transition"
                >
                  <input
                    type="checkbox"
                    :checked="selectedPermissionIds.has(perm.id)"
                    class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0"
                    @change="togglePermission(perm.id)"
                  />
                  <span class="text-sm text-white/80 font-mono">{{ perm.name }}</span>
                </label>
              </div>
            </div>
            <div
              v-if="sortedModules.length === 0"
              class="text-center py-12 text-white/50"
            >
              <p>No permissions found matching "{{ matrixSearchQuery }}"</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sticky bottom bar: Unsaved changes (only for editable roles) -->
    <div
      v-if="hasUnsavedChanges && selectedRole?.is_editable"
      class="sticky bottom-0 left-0 right-0 z-40 flex items-center justify-between gap-4 rounded-xl border border-amber-500/50 bg-amber-900/30 px-6 py-4 shadow-lg"
    >
      <span class="text-amber-200 font-medium">Unsaved changes</span>
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="discardChanges"
          class="px-4 py-2 rounded-lg font-medium text-amber-200 hover:bg-amber-800/50 transition"
        >
          Discard
        </button>
        <button
          type="button"
          :disabled="saveForm.processing"
          class="px-4 py-2 rounded-lg font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
          @click="savePermissions"
        >
          {{ saveForm.processing ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>

    <!-- Create Role Modal -->
    <Teleport to="body">
      <div
        v-show="showCreateModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        aria-modal="true"
        role="dialog"
        aria-labelledby="create-role-title"
      >
        <div
          class="fixed inset-0 bg-black/60 backdrop-blur-sm"
          aria-hidden="true"
          @click="closeCreateModal"
        />
        <div
          class="relative w-full max-w-md rounded-xl bg-gray-900 border border-gray-700 shadow-2xl p-6"
          @click.stop
        >
          <h2 id="create-role-title" class="text-lg font-semibold text-white mb-4">Create Role</h2>
          <form @submit.prevent="submitCreateRole" class="space-y-4">
            <div>
              <label for="role-name" class="block text-sm font-medium text-white/80 mb-1">Role Name</label>
              <input
                id="role-name"
                v-model="displayName"
                type="text"
                placeholder="e.g. WordPress Developer"
                class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-600 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :class="{ 'border-red-500': createRoleForm.errors.name }"
              />
              <p v-if="createRoleForm.errors.name" class="mt-1 text-sm text-red-400">
                {{ createRoleForm.errors.name }}
              </p>
              <p v-if="effectiveSlug" class="mt-1 text-xs text-white/50">
                This will create role key: <span class="font-mono text-indigo-300">{{ effectiveSlug }}</span>
              </p>
              <button
                v-if="!showSlugOverride"
                type="button"
                class="mt-1 text-xs text-indigo-400 hover:text-indigo-300 transition"
                @click="showSlugOverride = true; slugOverride = slugFromDisplay"
              >
                Customize key
              </button>
              <div v-else class="mt-2">
                <label for="role-slug" class="block text-xs font-medium text-white/60 mb-1">Role key (optional override)</label>
                <input
                  id="role-slug"
                  :value="slugOverride"
                  type="text"
                  placeholder="wordpress-developer"
                  class="w-full px-3 py-1.5 text-sm rounded-lg bg-gray-800 border border-gray-600 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  @input="onSlugOverrideInput"
                />
              </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="closeCreateModal"
                class="px-4 py-2 rounded-lg text-white/80 hover:text-white hover:bg-gray-800 transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="createRoleForm.processing || !effectiveSlug"
                class="px-4 py-2 rounded-lg font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                {{ createRoleForm.processing ? 'Creating...' : 'Create' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Edit Role Modal -->
    <Teleport to="body">
      <div
        v-show="showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        aria-modal="true"
        role="dialog"
        aria-labelledby="edit-role-title"
      >
        <div
          class="fixed inset-0 bg-black/60 backdrop-blur-sm"
          aria-hidden="true"
          @click="closeEditModal"
        />
        <div
          class="relative w-full max-w-md rounded-xl bg-gray-900 border border-gray-700 shadow-2xl p-6"
          @click.stop
        >
          <h2 id="edit-role-title" class="text-lg font-semibold text-white mb-4">Edit Role Name</h2>
          <form @submit.prevent="submitEditRole" class="space-y-4">
            <div>
              <label for="edit-role-name" class="block text-sm font-medium text-white/80 mb-1">Role Name</label>
              <input
                id="edit-role-name"
                v-model="editDisplayName"
                type="text"
                placeholder="e.g. WordPress Developer"
                class="w-full px-3 py-2 rounded-lg bg-gray-800 border border-gray-600 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :class="{ 'border-red-500': editRoleForm.errors.name }"
              />
              <p v-if="editRoleForm.errors.name" class="mt-1 text-sm text-red-400">
                {{ editRoleForm.errors.name }}
              </p>
              <p v-if="effectiveEditSlug" class="mt-1 text-xs text-white/50">
                Role key: <span class="font-mono text-indigo-300">{{ effectiveEditSlug }}</span>
              </p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="closeEditModal"
                class="px-4 py-2 rounded-lg text-white/80 hover:text-white hover:bg-gray-800 transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="editRoleForm.processing || !effectiveEditSlug"
                class="px-4 py-2 rounded-lg font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                {{ editRoleForm.processing ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete Role Confirm -->
    <Teleport to="body">
      <div
        v-show="showDeleteConfirm"
        class="fixed inset-0 z-[55] flex items-center justify-center p-4"
        aria-modal="true"
        role="dialog"
        aria-labelledby="delete-role-title"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDeleteConfirm" />
        <div class="relative w-full max-w-md rounded-xl bg-gray-900 border border-gray-700 p-6">
          <h2 id="delete-role-title" class="text-lg font-semibold text-white mb-2">Delete Role</h2>
          <p class="text-sm text-white/70 mb-4">
            Are you sure you want to delete the role "{{ selectedRole?.name?.replace(/-/g, ' ') }}"?
            This cannot be undone.
          </p>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              @click="closeDeleteConfirm"
              class="px-4 py-2 rounded-lg text-white/80 hover:text-white hover:bg-gray-800 transition"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="deleteForm.processing"
              @click="submitDeleteRole"
              class="px-4 py-2 rounded-lg font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 transition"
            >
              {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Leave with unsaved confirm -->
    <Teleport to="body">
      <div
        v-show="showLeaveConfirm"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        aria-modal="true"
        role="dialog"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="cancelLeave" />
        <div class="relative w-full max-w-md rounded-xl bg-gray-900 border border-gray-700 shadow-2xl p-6">
          <h2 class="text-lg font-semibold text-white mb-2">Unsaved changes</h2>
          <p class="text-sm text-white/70 mb-4">
            You have unsaved permission changes. Are you sure you want to leave?
          </p>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              @click="cancelLeave"
              class="px-4 py-2 rounded-lg text-white/80 hover:text-white hover:bg-gray-800 transition"
            >
              Stay
            </button>
            <button
              type="button"
              @click="confirmLeave"
              class="px-4 py-2 rounded-lg font-medium bg-red-600 text-white hover:bg-red-700 transition"
            >
              Leave anyway
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </PageShell>
</template>

<style scoped>
.glass-card {
  background: rgba(17, 24, 39, 0.8);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
}
</style>
