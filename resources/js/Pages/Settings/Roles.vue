<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
const flash = computed(() => (page.props as { flash?: { success?: string } }).flash)

const org = computed(() => {
  const routeGlobal = (window as any).route
  const params = routeGlobal?.()?.params ?? {}
  if (params.organization) return params.organization
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

const r = (name: string, params: Record<string, string> = {}) =>
  (window as any).route ? (window as any).route(name, { ...params, organization: org.value }) : '#'

// Matrix state: roleId -> Set of permission ids
const matrix = ref<Record<number, Set<number>>>({})

function buildMatrix() {
  const m: Record<number, Set<number>> = {}
  props.roles.forEach((role) => {
    m[role.id] = new Set(role.permission_ids)
  })
  matrix.value = m
}

watch(
  () => [props.roles, props.permissions],
  () => buildMatrix(),
  { immediate: true }
)

function isChecked(roleId: number, permId: number): boolean {
  return matrix.value[roleId]?.has(permId) ?? false
}

function toggle(roleId: number, permId: number) {
  if (!matrix.value[roleId]) matrix.value[roleId] = new Set()
  if (matrix.value[roleId].has(permId)) {
    matrix.value[roleId].delete(permId)
  } else {
    matrix.value[roleId].add(permId)
  }
  matrix.value = { ...matrix.value }
}

// Module groups for display (matches seeded permissions)
const MODULE_GROUPS: Record<string, string[]> = {
  Projects: ['projects'],
  Finance: ['financials'],
  Users: ['users'],
  Clients: ['clients'],
  Contacts: ['contacts'],
  Roles: ['roles'],
}

const groupedForDisplay = computed(() => {
  const groups: { label: string; modules: string[] }[] = []
  const seen = new Set<string>()
  for (const [label, modules] of Object.entries(MODULE_GROUPS)) {
    const existing = modules.filter((m) => props.groupedPermissions[m]?.length)
    if (existing.length) {
      groups.push({ label, modules: existing })
      existing.forEach((m) => seen.add(m))
    }
  }
  const other = Object.keys(props.groupedPermissions).filter((m) => !seen.has(m))
  if (other.length) groups.push({ label: 'Other', modules: other.sort() })
  return groups
})

const form = useForm<{ matrix: Record<string, number[]> }>({
  matrix: {},
})

function saveChanges() {
  const payload: Record<string, number[]> = {}
  props.roles.forEach((role) => {
    payload[String(role.id)] = Array.from(matrix.value[role.id] ?? [])
  })
  form.matrix = payload
  form.put(r('roles.update'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="space-y-6">
    <section class="hero-slab">
      <div class="flex items-end justify-between gap-6">
        <div>
          <div class="text-sm text-white/60">Settings • {{ org }}</div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">Roles & Permissions</h1>
          <p class="mt-1 text-white/60">
            Matrix: assign permissions to roles. Save changes to apply.
          </p>
        </div>
        <button
          type="button"
          :disabled="form.processing"
          @click="saveChanges"
          :class="[
            'px-4 py-2 rounded-lg font-medium transition',
            form.processing
              ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
              : 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg'
          ]"
        >
          {{ form.processing ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </section>

    <div v-if="flash?.success" class="rounded-lg bg-green-900/30 border border-green-700/50 px-4 py-2 text-green-200 text-sm">
      {{ flash.success }}
    </div>

    <div class="glass-card overflow-x-auto">
      <table class="w-full min-w-[800px] border-collapse">
        <thead>
          <tr class="border-b border-gray-700">
            <th class="text-left py-3 px-4 text-white/80 font-semibold">Permission</th>
            <th v-for="role in roles" :key="role.id" class="py-3 px-4 text-center text-white/80 font-semibold">
              <span class="capitalize">{{ role.name.replace(/-/g, ' ') }}</span>
              <span
                v-if="role.is_team_scoped"
                class="ml-1 text-xs text-white/50"
              >(team)</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groupedForDisplay" :key="group.label">
            <tr class="bg-gray-800/30">
              <td colspan="100" class="py-2 px-4 text-sm font-semibold text-indigo-300">
                {{ group.label }}
              </td>
            </tr>
            <tr
              v-for="perm in group.modules.flatMap((m) => groupedPermissions[m] || [])"
              :key="perm.id"
              class="border-b border-gray-700/50 hover:bg-gray-800/20"
            >
              <td class="py-2 px-4 text-sm text-white/90 font-mono">{{ perm.name }}</td>
              <td
                v-for="role in roles"
                :key="role.id"
                class="py-2 px-4 text-center"
              >
                <input
                  type="checkbox"
                  :checked="isChecked(role.id, perm.id)"
                  @change="toggle(role.id, perm.id)"
                  class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 cursor-pointer"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
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
