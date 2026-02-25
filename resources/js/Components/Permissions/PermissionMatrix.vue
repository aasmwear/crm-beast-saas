<script setup lang="ts">
import { computed } from 'vue'
import type {
  PermissionCatalog,
  CatalogModule,
  CatalogSpecial,
  Permission,
} from '@/lib/permissionCatalog'
import { buildPermissionNameToId } from '@/lib/permissionCatalog'

const props = withDefaults(
  defineProps<{
    catalog: PermissionCatalog
    permissions: Permission[]
    selectedPermissionIds: Set<number>
    searchQuery?: string
    expandedModules?: Set<string>
  }>(),
  {
    searchQuery: '',
    expandedModules: () => new Set<string>(),
  }
)

const emit = defineEmits<{
  toggle: [permissionId: number]
  toggleModule: [moduleKey: string]
  save: []
  discard: []
}>()

const nameToId = computed(() => buildPermissionNameToId(props.permissions))

function isSelected(permissionName: string): boolean {
  const id = nameToId.value[permissionName]
  return id != null && props.selectedPermissionIds.has(id)
}

function toggle(permissionName: string) {
  const id = nameToId.value[permissionName]
  if (id != null) emit('toggle', id)
}

function matchesSearch(module: CatalogModule, specials: CatalogSpecial[]): boolean {
  if (!props.searchQuery.trim()) return true
  const q = props.searchQuery.toLowerCase()
  if (module.label.toLowerCase().includes(q) || module.key.toLowerCase().includes(q)) return true
  return specials.some(s => s.label.toLowerCase().includes(q) || s.permission.toLowerCase().includes(q))
}

const filteredModules = computed(() => {
  return props.catalog.modules
    .filter(m => matchesSearch(m, props.catalog.specials.filter(s => s.module === m.key)))
    .sort((a, b) => a.sortOrder - b.sortOrder)
})

function getModuleSpecials(moduleKey: string): CatalogSpecial[] {
  return props.catalog.specials.filter(s => s.module === moduleKey)
}

function getModuleMatrixPerms(moduleKey: string): { actionKey: string; actionLabel: string; permission: string }[] {
  const matrix = props.catalog.matrix[moduleKey]
  if (!matrix) return []
  return props.catalog.actions
    .filter(a => matrix[a.key])
    .map(a => ({ actionKey: a.key, actionLabel: a.label, permission: matrix[a.key] }))
}

function getModuleSelectedCount(moduleKey: string): number {
  const matrixPerms = getModuleMatrixPerms(moduleKey).map(p => p.permission)
  const specialPerms = getModuleSpecials(moduleKey).map(s => s.permission)
  const all = [...matrixPerms, ...specialPerms]
  let count = 0
  for (const name of all) {
    const id = nameToId.value[name]
    if (id != null && props.selectedPermissionIds.has(id)) count++
  }
  return count
}

function getModuleTotalCount(moduleKey: string): number {
  const matrixPerms = getModuleMatrixPerms(moduleKey)
  const specialPerms = getModuleSpecials(moduleKey)
  return matrixPerms.length + specialPerms.length
}

function isExpanded(moduleKey: string): boolean {
  return props.expandedModules?.has(moduleKey) ?? false
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="module in filteredModules"
      :key="module.key"
      class="border border-gray-700/50 rounded-lg bg-gray-900/30 overflow-hidden"
    >
      <!-- Module header -->
      <div
        class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-800/30 transition"
        @click="emit('toggleModule', module.key)"
      >
        <div class="flex items-center gap-3">
          <span class="text-xl">{{ module.icon }}</span>
          <div>
            <h4 class="text-sm font-semibold text-white">{{ module.label }}</h4>
            <p class="text-xs text-white/50">
              {{ getModuleSelectedCount(module.key) }} / {{ getModuleTotalCount(module.key) }} selected
            </p>
          </div>
        </div>
        <svg
          :class="[
            'w-5 h-5 text-white/60 transition-transform',
            isExpanded(module.key) ? 'rotate-180' : ''
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

      <!-- Module body: matrix cells + specials -->
      <div
        v-show="isExpanded(module.key)"
        class="p-4 pt-0 space-y-4"
      >
        <!-- Action matrix -->
        <div
          v-if="getModuleMatrixPerms(module.key).length"
          class="flex flex-wrap gap-2"
        >
          <button
            v-for="{ actionKey, actionLabel, permission } in getModuleMatrixPerms(module.key)"
            :key="actionKey"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium transition',
              isSelected(permission)
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-800/50 text-white/70 hover:bg-gray-700 hover:text-white'
            ]"
            @click.stop="toggle(permission)"
          >
            {{ actionLabel }}
          </button>
        </div>

        <!-- Special permissions -->
        <div
          v-if="getModuleSpecials(module.key).length"
          class="flex flex-wrap gap-2"
        >
          <span class="text-xs text-white/40 self-center mr-2">Special:</span>
          <button
            v-for="s in getModuleSpecials(module.key)"
            :key="s.permission"
            type="button"
            :class="[
              'px-2.5 py-1 rounded-full text-xs font-medium transition',
              isSelected(s.permission)
                ? 'bg-amber-600/80 text-white'
                : 'bg-gray-800/50 text-white/60 hover:bg-gray-700 hover:text-white'
            ]"
            @click.stop="toggle(s.permission)"
          >
            {{ s.label }}
          </button>
        </div>

        <p
          v-if="getModuleMatrixPerms(module.key).length === 0 && getModuleSpecials(module.key).length === 0"
          class="text-xs text-white/40"
        >
          No catalog permissions for this module.
        </p>
      </div>
    </div>

    <div
      v-if="filteredModules.length === 0"
      class="text-center py-12 text-white/50"
    >
      <p>No modules match "{{ searchQuery }}"</p>
    </div>
  </div>
</template>
