<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage<{ auth?: { is_admin?: boolean; can?: { activity?: boolean; reports?: boolean; attendance?: boolean } } }>()
const isAdmin = computed(() => !!page.props.auth?.is_admin)
const can = computed(() => page.props.auth?.can ?? { activity: false, reports: false, attendance: false })

const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, string> = {}) =>
  routeGlobal ? routeGlobal(name, params) : '#'

const org = computed(() => {
  try {
    const fn = routeGlobal
    const p = (typeof fn === 'function' ? fn() : null)?.params ?? {}
    if (p.organization) return p.organization
  } catch {
    // ignore
  }
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

type NavItem = {
  label: string
  routeName: string
  adminOnly?: boolean
  permission?: 'activity' | 'reports' | 'attendance'
}

const allNavItems: NavItem[] = [
  { label: 'Dashboard', routeName: 'dashboard' },
  { label: 'Clients', routeName: 'clients.index' },
  { label: 'Projects', routeName: 'projects.index' },
  { label: 'Tasks', routeName: 'tasks.board' },
  { label: 'Announcements', routeName: 'announcements.index' },
  { label: 'Activity', routeName: 'activity.index', permission: 'activity' },
  { label: 'Reports', routeName: 'reports.index', permission: 'reports' },
  { label: 'Employees', routeName: 'hrm.index', adminOnly: true },
  { label: 'Attendance', routeName: 'attendance.index', permission: 'attendance' },
  { label: 'Settings', routeName: 'settings.index', adminOnly: true },
  { label: 'Billing', routeName: 'billing.index', adminOnly: true },
]

const open = ref(false)
const query = ref('')
const selectedIndex = ref(0)
const inputEl = ref<HTMLInputElement | null>(null)
const listEl = ref<HTMLDivElement | null>(null)

const visibleItems = computed(() => {
  let items = allNavItems.filter((item) => {
    if (item.adminOnly && !isAdmin.value) return false
    if (item.permission === 'activity' && !can.value.activity) return false
    if (item.permission === 'reports' && !can.value.reports) return false
    if (item.permission === 'attendance' && !can.value.attendance) return false
    return true
  })
  const q = query.value.trim().toLowerCase()
  if (q) {
    items = items.filter((item) =>
      item.label.toLowerCase().includes(q),
    )
  }
  return items
})

const hasResults = computed(() => visibleItems.value.length > 0)

watch(open, (isOpen) => {
  if (isOpen) {
    query.value = ''
    selectedIndex.value = 0
    nextTick(() => inputEl.value?.focus())
  }
})

watch(visibleItems, () => {
  selectedIndex.value = Math.min(
    selectedIndex.value,
    Math.max(0, visibleItems.value.length - 1),
  )
})

watch(selectedIndex, () => {
  nextTick(() => {
    const selected = listEl.value?.querySelector('[data-selected]')
    selected?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  })
})

function close() {
  open.value = false
}

function goTo(item: NavItem) {
  const url = r(item.routeName, { organization: org.value })
  router.visit(url)
  close()
}

function onKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    open.value = !open.value
    return
  }

  if (!open.value) return

  if (e.key === 'Escape') {
    e.preventDefault()
    close()
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(
      selectedIndex.value + 1,
      visibleItems.value.length - 1,
    )
    return
  }

  if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
    return
  }

  if (e.key === 'Enter' && hasResults.value) {
    e.preventDefault()
    const item = visibleItems.value[selectedIndex.value]
    if (item) goTo(item)
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh] px-4"
        role="dialog"
        aria-modal="true"
        aria-label="Command palette"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm"
          @click="close"
        />

        <!-- Modal -->
        <div
          class="relative w-full max-w-xl rounded-2xl border border-white/10 bg-slate-900/95 shadow-2xl ring-1 ring-white/5"
          @click.stop
        >
          <!-- Search input -->
          <div class="flex items-center gap-3 border-b border-white/10 px-4 py-3">
            <svg
              class="h-5 w-5 shrink-0 text-white/40"
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
              ref="inputEl"
              v-model="query"
              type="text"
              placeholder="Search navigation…"
              class="min-w-0 flex-1 bg-transparent text-base text-white placeholder-white/40 focus:outline-none"
            />
            <kbd class="hidden rounded bg-white/10 px-2 py-1 text-xs text-white/50 sm:inline">Esc</kbd>
          </div>

          <!-- List -->
          <div
            ref="listEl"
            class="max-h-[min(60vh,320px)] overflow-y-auto py-2"
          >
            <template v-if="hasResults">
              <Link
                v-for="(item, index) in visibleItems"
                :key="item.routeName"
                :href="r(item.routeName, { organization: org })"
                :data-selected="index === selectedIndex ? 'true' : undefined"
                :class="[
                  'flex items-center gap-3 px-4 py-2.5 text-sm transition',
                  index === selectedIndex
                    ? 'bg-violet-600/30 text-white'
                    : 'text-white/80 hover:bg-white/5 hover:text-white',
                ]"
                @click.prevent="goTo(item)"
              >
                <span class="truncate">{{ item.label }}</span>
              </Link>
            </template>
            <div
              v-else
              class="px-4 py-8 text-center text-sm text-white/50"
            >
              No results for “{{ query }}”
            </div>
          </div>

          <div class="border-t border-white/10 px-4 py-2 text-xs text-white/40">
            <span class="opacity-80">↑↓</span> Navigate
            <span class="mx-2">·</span>
            <span class="opacity-80">↵</span> Open
            <span class="mx-2">·</span>
            <span class="opacity-80">⌘K</span> Toggle
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
