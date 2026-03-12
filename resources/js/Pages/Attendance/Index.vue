<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

defineOptions({ layout: AuthenticatedLayout })

interface AttendanceRecord {
  id: number
  clock_in_at: string | null
  clock_out_at: string | null
  minutes: number | null
  status?: string | null
  notes?: string | null
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedAttendance {
  data: AttendanceRecord[]
  links: PaginationLink[]
}

interface AttendanceFilters {
  user_id?: number | string | null
  date_from?: string | null
  date_to?: string | null
  status?: string | null
}

interface SimpleUser {
  id: number
  name: string
}

const props = defineProps<{
  attendance: PaginatedAttendance
  current: AttendanceRecord | null
  filters?: AttendanceFilters
  users?: SimpleUser[]
  canCreate?: boolean
  canEdit?: boolean
  canDelete?: boolean
  canManage?: boolean
  canViewAll?: boolean
}>()

// Ziggy wrapper
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  // @ts-ignore
  (window as any).route(name, params, absolute, config)

// Resolve org slug from Ziggy or URL
const org = computed(() => {
  // @ts-ignore
  const p = (window as any).route?.()?.params ?? {}
  if (p.organization) return p.organization as string
  const parts = window.location.pathname.split('/').filter(Boolean)
  const idx = parts.indexOf('org')
  return idx >= 0 && parts[idx + 1] ? parts[idx + 1] : 'acme'
})

// Local filter state for history view
const selectedUserId = ref<string>(
  props.filters?.user_id ? String(props.filters.user_id) : '',
)
const dateFrom = ref<string>(props.filters?.date_from ?? '')
const dateTo = ref<string>(props.filters?.date_to ?? '')
const statusFilter = ref<string>(props.filters?.status ?? '')

const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Any status' },
  { value: 'open', label: 'Open' },
  { value: 'closed', label: 'Closed' },
  { value: 'approved', label: 'Approved' },
]

function applyFilters() {
  router.get(
    r('attendance.index', { organization: org.value }),
    {
      user_id: selectedUserId.value || null,
      date_from: dateFrom.value || null,
      date_to: dateTo.value || null,
      status: statusFilter.value || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  )
}

function resetFilters() {
  selectedUserId.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  statusFilter.value = ''
  applyFilters()
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString(undefined, {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  })
}

function formatTime(value: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDuration(value: number | null): string {
  if (value === null || Number.isNaN(value)) return '—'
  const total = Math.max(0, Math.round(value))
  const hrs = Math.floor(total / 60)
  const mins = total % 60
  if (hrs && mins) return `${hrs}h ${mins}m`
  if (hrs) return `${hrs}h`
  return `${mins}m`
}

function statusClass(status?: string | null): string {
  const value = (status ?? '').toLowerCase()
  if (!value) {
    return 'bg-white/5 text-white/60 border border-white/10'
  }
  if (['open', 'working', 'pending'].includes(value)) {
    return 'bg-amber-500/15 text-amber-300 border border-amber-500/40'
  }
  if (['closed', 'complete', 'approved'].includes(value)) {
    return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40'
  }
  if (['rejected', 'error'].includes(value)) {
    return 'bg-rose-500/15 text-rose-300 border border-rose-500/40'
  }
  return 'bg-white/5 text-white/70 border border-white/15'
}

// Clock in/out loading state to prevent double-submit
const isClocking = ref(false)

function clockIn() {
  if (isClocking.value) return
  isClocking.value = true
  router.post(
    r('attendance.clockIn', { organization: org.value }),
    {},
    {
      preserveScroll: true,
      onFinish: () => { isClocking.value = false },
    },
  )
}

function clockOut() {
  if (isClocking.value) return
  isClocking.value = true
  router.post(
    r('attendance.clockOut', { organization: org.value }),
    {},
    {
      preserveScroll: true,
      onFinish: () => { isClocking.value = false },
    },
  )
}

// --- HR / Manager manual corrections (status + notes) ---

const editRecord = ref<AttendanceRecord | null>(null)
const isEditOpen = ref(false)

const editForm = useForm({
  status: '' as string | null,
  notes: '' as string | null,
})

function openEdit(record: AttendanceRecord) {
  editRecord.value = record
  editForm.status = record.status ?? ''
  editForm.notes = record.notes ?? ''
  isEditOpen.value = true
}

function closeEdit() {
  isEditOpen.value = false
  editRecord.value = null
  editForm.reset()
  editForm.clearErrors()
}

function saveEdit() {
  if (!editRecord.value) return

  editForm.patch(
    r('attendance.update', {
      organization: org.value,
      attendance: editRecord.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        closeEdit()
      },
    },
  )
}

function approveRecord(record: AttendanceRecord) {
  router.post(
    r('attendance.approve', { organization: org.value, attendance: record.id }),
    {},
    { preserveScroll: true },
  )
}

function deleteRecord(record: AttendanceRecord) {
  if (!confirm('Delete this attendance record? This cannot be undone.')) return
  router.delete(
    r('attendance.destroy', { organization: org.value, attendance: record.id }),
    { preserveScroll: true },
  )
}
</script>

<template>
  <PageShell
    :header="{
      breadcrumb: `Organization • ${String(org).toUpperCase()}`,
      title: 'Attendance',
      subtitle: 'Track your daily check-ins and total hours.',
    }"
  >
    <template #header-actions>
      <div v-if="props.canCreate !== false" class="flex items-center gap-3">
        <button
          v-if="!props.current"
          type="button"
          :disabled="isClocking"
          class="rounded-full border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-xs font-medium text-emerald-200 hover:bg-emerald-500/25 disabled:cursor-not-allowed disabled:opacity-60"
          @click="clockIn"
        >
          {{ isClocking ? '…' : '⏱ Clock in' }}
        </button>
        <button
          v-else
          type="button"
          :disabled="isClocking"
          class="rounded-full border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-medium text-rose-200 hover:bg-rose-500/25 disabled:cursor-not-allowed disabled:opacity-60"
          @click="clockOut"
        >
          {{ isClocking ? '…' : '⏹ Clock out' }}
        </button>
      </div>
    </template>

    <!-- Today status -->
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-black/40 p-4 backdrop-blur">
      <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-white/50">
            Today
          </p>
          <p class="mt-1 text-sm text-white/80">
            {{ formatDate(props.current?.clock_in_at ?? null) }}
          </p>

          <p v-if="props.current" class="mt-2 text-xs text-white/60">
            Clocked in at
            <span class="font-medium text-white">
              {{ formatTime(props.current.clock_in_at) }}
            </span>
            <span v-if="props.current.clock_out_at">
              &mdash; clocked out at
              <span class="font-medium text-white">
                {{ formatTime(props.current.clock_out_at) }}
              </span>
            </span>
          </p>

          <p v-else class="mt-2 text-xs text-white/60">
            You are currently
            <span class="font-semibold text-rose-200">
              clocked out
            </span>
            . Hit
            <span class="font-semibold text-emerald-200">
              Clock in
            </span>
            when your shift starts.
          </p>
        </div>

        <div class="flex items-center gap-6 text-xs text-white/60">
          <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
            <div class="text-[10px] uppercase tracking-wide text-white/40">
              Session duration
            </div>
            <div class="mt-1 text-sm font-semibold text-white">
              {{ formatDuration(props.current?.minutes ?? null) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- History table -->
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-black/30 backdrop-blur">
      <!-- History header + filters -->
      <div
        class="flex flex-col gap-3 border-b border-white/10 bg-white/[0.02] px-4 py-3 md:flex-row md:items-center md:justify-between"
      >
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-white/50">
            History
          </p>
          <p class="mt-1 text-[11px] text-white/40">
            Recent attendance records
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs text-white/60">
          <!-- User filter (HR/Admin only) -->
          <div v-if="props.canViewAll" class="flex items-center gap-2">
            <span class="hidden sm:inline">User</span>
            <select
              v-model="selectedUserId"
              class="rounded-lg border border-white/15 bg-black/40 px-2 py-1 text-xs text-white/80 focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
              <option value="">
                Me
              </option>
              <option
                v-for="user in props.users || []"
                :key="user.id"
                :value="String(user.id)"
              >
                {{ user.name }}
              </option>
            </select>
          </div>

          <!-- Date range -->
          <div class="flex items-center gap-1">
            <span class="hidden sm:inline">From</span>
            <input
              v-model="dateFrom"
              type="date"
              class="rounded-lg border border-white/15 bg-black/40 px-2 py-1 text-xs text-white/80 focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
          </div>

          <div class="flex items-center gap-1">
            <span class="hidden sm:inline">To</span>
            <input
              v-model="dateTo"
              type="date"
              class="rounded-lg border border-white/15 bg-black/40 px-2 py-1 text-xs text-white/80 focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
          </div>

          <!-- Status filter -->
          <div class="flex items-center gap-2">
            <span class="hidden sm:inline">Status</span>
            <select
              v-model="statusFilter"
              class="rounded-lg border border-white/15 bg-black/40 px-2 py-1 text-xs text-white/80 focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
              <option
                v-for="opt in STATUS_OPTIONS"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <!-- Actions -->
          <button
            type="button"
            class="inline-flex items-center rounded-full border border-white/20 bg-white/5 px-3 py-1 text-[11px] font-medium text-white hover:bg-white/10"
            @click="applyFilters"
          >
            Apply
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded-full px-2 py-1 text-[11px] text-white/40 hover:text-white/80"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wide text-white/60">
            <tr>
              <th class="px-4 py-3">
                Date
              </th>
              <th class="px-4 py-3">
                Clock in
              </th>
              <th class="px-4 py-3">
                Clock out
              </th>
              <th class="px-4 py-3">
                Duration
              </th>
              <th class="px-4 py-3">
                Status
              </th>
              <th class="px-4 py-3">
                Notes
              </th>
              <th v-if="props.canEdit || props.canManage || props.canDelete" class="px-4 py-3 text-right">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in props.attendance.data"
              :key="row.id"
              class="border-t border-white/5 hover:bg-white/5/10"
            >
              <td class="px-4 py-3 align-top">
                {{ formatDate(row.clock_in_at) }}
              </td>
              <td class="px-4 py-3 align-top">
                {{ formatTime(row.clock_in_at) }}
              </td>
              <td class="px-4 py-3 align-top">
                {{ formatTime(row.clock_out_at) }}
              </td>
              <td class="px-4 py-3 align-top">
                <span class="rounded-md bg-white/5 px-2 py-1 text-xs text-white/80">
                  {{ formatDuration(row.minutes) }}
                </span>
              </td>
              <td class="px-4 py-3 align-top">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                  :class="statusClass(row.status)"
                >
                  {{ row.status ?? '—' }}
                </span>
              </td>
              <td class="px-4 py-3 align-top text-xs text-white/70">
                <span v-if="row.notes">
                  {{ row.notes.length > 80 ? row.notes.slice(0, 80) + '…' : row.notes }}
                </span>
                <span v-else class="text-white/30">
                  —
                </span>
              </td>
              <td v-if="props.canEdit || props.canManage || props.canDelete" class="px-4 py-3 align-top text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="props.canManage && row.status === 'closed'"
                    type="button"
                    class="inline-flex items-center rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-[11px] font-medium text-emerald-200 hover:bg-emerald-500/20"
                    @click="approveRecord(row)"
                  >
                    Approve
                  </button>
                  <button
                    v-if="props.canEdit"
                    type="button"
                    class="inline-flex items-center rounded-full border border-white/20 bg-white/5 px-3 py-1 text-[11px] font-medium text-white hover:bg-white/10"
                    @click="openEdit(row)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="props.canDelete"
                    type="button"
                    class="inline-flex items-center rounded-full border border-rose-500/40 bg-rose-500/10 px-3 py-1 text-[11px] font-medium text-rose-200 hover:bg-rose-500/20"
                    @click="deleteRecord(row)"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!props.attendance.data.length">
              <td :colspan="(props.canEdit || props.canManage || props.canDelete) ? 7 : 6" class="px-4 py-10 text-center text-sm text-white/50">
                No attendance records yet. As you check in and out, your history will appear here.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="props.attendance.links && props.attendance.links.length > 1"
        class="border-t border-white/10 bg-black/40 px-4 py-3"
      >
        <div class="flex justify-between text-xs text-white/60">
          <div />
          <div class="flex flex-wrap gap-1">
            <template
              v-for="link in props.attendance.links"
              :key="link.label"
            >
              <button
                v-if="link.url"
                type="button"
                class="rounded-md px-3 py-1.5 text-xs font-medium"
                :class="[
                  link.active
                    ? 'bg-indigo-500 text-white'
                    : 'bg-white/5 text-white/70 hover:bg-white/10',
                ]"
                v-html="link.label"
                @click="router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
              />
              <span
                v-else
                class="rounded-md px-3 py-1.5 text-xs text-white/40"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit modal -->
    <div
      v-if="isEditOpen && editRecord"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl border border-white/15 bg-black/90 p-5 text-sm text-white shadow-xl">
        <div class="mb-3 flex items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold">
              Edit attendance
            </h2>
            <p class="mt-1 text-xs text-white/60">
              Update status or add an HR note for this record.
            </p>
          </div>
          <button
            type="button"
            class="text-xs text-white/40 hover:text-white/80"
            @click="closeEdit"
          >
            ✕
          </button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-white/70">
              Status
            </label>
            <select
              v-model="editForm.status"
              class="w-full rounded-lg border border-white/20 bg-black/60 px-3 py-2 text-xs text-white focus:border-emerald-400 focus:outline-none focus:ring-0"
            >
              <option value="">
                Keep as is
              </option>
              <option value="open">
                Open
              </option>
              <option value="closed">
                Closed
              </option>
              <option value="approved">
                Approved
              </option>
            </select>
            <p
              v-if="editForm.errors.status"
              class="mt-1 text-[11px] text-rose-300"
            >
              {{ editForm.errors.status }}
            </p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-white/70">
              HR note
            </label>
            <textarea
              v-model="editForm.notes"
              rows="3"
              class="w-full resize-none rounded-lg border border-white/20 bg-black/60 px-3 py-2 text-xs text-white focus:border-emerald-400 focus:outline-none focus:ring-0"
              placeholder="Example: Corrected check-out time due to system issue."
            />
            <p
              v-if="editForm.errors.notes"
              class="mt-1 text-[11px] text-rose-300"
            >
              {{ editForm.errors.notes }}
            </p>
          </div>
        </div>

        <div class="mt-5 flex items-center justify-end gap-2 text-xs">
          <button
            type="button"
            class="rounded-full px-3 py-1.5 text-white/60 hover:text-white"
            @click="closeEdit"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-full border border-emerald-400/60 bg-emerald-500/20 px-4 py-1.5 font-medium text-emerald-100 hover:bg-emerald-500/40"
            :disabled="editForm.processing"
            @click="saveEdit"
          >
            Save changes
          </button>
        </div>
      </div>
    </div>
  </PageShell>
</template>
