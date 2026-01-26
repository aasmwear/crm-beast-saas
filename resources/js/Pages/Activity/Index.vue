<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface OrganizationProps {
  id: number
  name: string
  slug: string
}

interface ActivityItem {
  id: number
  action: string
  entity: string
  created_at: string
  actor?: string | null
  actor_id?: number | null
}

interface ActivityFilters {
  actor_id?: string | number | null
  entity?: string | null
  action?: string | null
  date_from?: string | null
  date_to?: string | null
}

interface SimpleUser {
  id: number
  name: string
}

const props = defineProps<{
  organization: OrganizationProps
  items: ActivityItem[]
  filters?: ActivityFilters
  users?: SimpleUser[]
}>()

// Ziggy wrapper
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  // @ts-ignore
  (window as any).route(name, params, absolute, config)

const orgSlug = computed(() => props.organization.slug)

// Local filter state
const actorId = ref<string>(props.filters?.actor_id ? String(props.filters.actor_id) : '')
const entityFilter = ref<string>(props.filters?.entity ?? '')
const actionFilter = ref<string>(props.filters?.action ?? '')
const dateFrom = ref<string>(props.filters?.date_from ?? '')
const dateTo = ref<string>(props.filters?.date_to ?? '')

function applyFilters() {
  router.get(
    r('activity.index', { organization: orgSlug.value }),
    {
      actor_id: actorId.value || null,
      entity: entityFilter.value || null,
      action: actionFilter.value || null,
      date_from: dateFrom.value || null,
      date_to: dateTo.value || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  )
}

function resetFilters() {
  actorId.value = ''
  entityFilter.value = ''
  actionFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  applyFilters()
}

function formatDateTime(value: string): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString(undefined, {
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function humanEntity(entity: string): string {
  if (!entity) return 'item'
  // simple mapping if needed later
  return entity.replace('_', ' ')
}

function humanAction(action: string): string {
  if (!action) return 'did something'
  return action.replace('_', ' ')
}

function actorLabel(item: ActivityItem): string {
  return item.actor || 'System'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
      <div>
        <h1 class="text-xl font-semibold text-white">
          Activity
        </h1>
        <p class="mt-1 text-sm text-white/60">
          Recent changes across clients, projects, tasks, attendance, and settings for
          <span class="font-medium text-white">{{ organization.name }}</span>.
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-black/40 p-4 backdrop-blur">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-white/50">
            Filters
          </p>
          <p class="mt-1 text-[11px] text-white/40">
            Narrow down the audit log by user, entity, action or date range.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs text-white/70">
          <!-- User filter -->
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline">User</span>
            <select
              v-model="actorId"
              class="rounded-lg border border-white/20 bg-black/60 px-3 py-1.5 text-xs text-white focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
              <option value="">
                All users
              </option>
              <option
                v-for="user in users || []"
                :key="user.id"
                :value="String(user.id)"
              >
                {{ user.name }}
              </option>
            </select>
          </div>

          <!-- Entity filter -->
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline">Entity</span>
            <input
              v-model="entityFilter"
              type="text"
              placeholder="client, project, task..."
              class="w-28 rounded-lg border border-white/20 bg-black/60 px-3 py-1.5 text-xs text-white placeholder:text-white/30 focus:border-emerald-400 focus:outline-none focus:ring-0 md:w-32"
            >
          </div>

          <!-- Action filter -->
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline">Action</span>
            <input
              v-model="actionFilter"
              type="text"
              placeholder="created, updated..."
              class="w-28 rounded-lg border border-white/20 bg-black/60 px-3 py-1.5 text-xs text-white placeholder:text-white/30 focus:border-emerald-400 focus:outline-none focus:ring-0 md:w-32"
            >
          </div>

          <!-- Date range -->
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline">From</span>
            <input
              v-model="dateFrom"
              type="date"
              class="rounded-lg border border-white/20 bg-black/60 px-2 py-1.5 text-xs text-white focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
          </div>
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline">To</span>
            <input
              v-model="dateTo"
              type="date"
              class="rounded-lg border border-white/20 bg-black/60 px-2 py-1.5 text-xs text-white focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
          </div>

          <!-- Actions -->
          <button
            type="button"
            class="inline-flex items-center rounded-full border border-white/30 bg-white/10 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-white/20"
            @click="applyFilters"
          >
            Apply
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded-full px-2 py-1.5 text-[11px] text-white/50 hover:text-white"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Activity list -->
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-black/30 backdrop-blur">
      <div class="border-b border-white/10 bg-white/[0.02] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-white/60">
        Recent activity
      </div>

      <div v-if="items.length" class="divide-y divide-white/5">
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-start gap-3 px-4 py-3"
        >
          <!-- Dot / timeline marker -->
          <div class="mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-emerald-400" />

          <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-1 text-xs">
                <span class="font-medium text-white">
                  {{ actorLabel(item) }}
                </span>
                <span class="text-white/40">
                  ·
                </span>
                <span class="text-white/70">
                  {{ humanAction(item.action) }}
                </span>
                <span class="text-white/40">
                  {{ humanEntity(item.entity) }}
                </span>
              </div>
            </div>

            <div class="flex flex-col items-end text-right">
              <span class="text-[11px] text-white/50">
                {{ formatDateTime(item.created_at) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div
        v-else
        class="px-4 py-10 text-center text-sm text-white/50"
      >
        No activity yet. As you create or update clients, projects, tasks or attendance, updates will appear here.
      </div>
    </div>
  </div>
</template>
