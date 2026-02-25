<!-- resources/js/Components/ui/IconRail.vue -->
<template>
  <!-- Mobile overlay + drawer -->
  <Teleport to="body">
    <Transition name="mobile-drawer">
      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-50 lg:hidden"
        aria-modal="true"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-sm"
          aria-hidden="true"
          @click="emit('close')"
        />
        <!-- Drawer panel -->
        <aside
          class="absolute left-0 top-0 bottom-0 w-64 bg-white text-[#0d0f14] shadow-2xl"
          @click.stop
        >
          <nav class="flex h-full flex-col gap-1 p-4" aria-label="Mobile menu">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm font-semibold text-[#0d0f14]">Menu</span>
              <button
                type="button"
                class="p-2 rounded-lg text-[#0d0f14]/60 hover:bg-[#0d0f14]/10"
                aria-label="Close menu"
                @click="emit('close')"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <Link
              :href="r('dashboard', { organization: org })"
              class="mobile-nav-link"
              :class="{ 'mobile-nav-link-active': isCurrent('dashboard') }"
              @click="emit('close')"
            >
              Dashboard
            </Link>
            <Link
              :href="r('clients.index', { organization: org })"
              class="mobile-nav-link"
              :class="{ 'mobile-nav-link-active': isCurrent('clients.index') }"
              @click="emit('close')"
            >
              Clients
            </Link>
            <Link
              :href="r('projects.index', { organization: org })"
              class="mobile-nav-link"
              :class="{ 'mobile-nav-link-active': isCurrent('projects.index') }"
              @click="emit('close')"
            >
              Projects
            </Link>
            <Link
              :href="r('tasks.board', { organization: org })"
              class="mobile-nav-link"
              :class="{ 'mobile-nav-link-active': isCurrentAny(['tasks.index', 'tasks.board']) }"
              @click="emit('close')"
            >
              Tasks
            </Link>
            <Link
              :href="r('announcements.index', { organization: org })"
              class="mobile-nav-link"
              :class="{ 'mobile-nav-link-active': isCurrent('announcements.index') }"
              @click="emit('close')"
            >
              Announcements
            </Link>
            <div v-if="isAdmin" class="border-t border-[#0d0f14]/10 my-2 pt-2">
              <Link v-if="canViewActivity" :href="r('activity.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('activity.index') }" @click="emit('close')">Activity</Link>
              <Link :href="r('hrm.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('hrm.index') }" @click="emit('close')">Employees</Link>
              <Link :href="r('attendance.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('attendance.index') }" @click="emit('close')">Attendance</Link>
              <Link :href="r('settings.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('settings.index') }" @click="emit('close')">Settings</Link>
              <Link :href="r('billing.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('billing.index') }" @click="emit('close')">Billing</Link>
            </div>
            <div v-else class="border-t border-[#0d0f14]/10 my-2 pt-2">
              <Link v-if="canViewActivity" :href="r('activity.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('activity.index') }" @click="emit('close')">Activity</Link>
            <Link :href="r('attendance.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isCurrent('attendance.index') }" @click="emit('close')">Attendance</Link>
            </div>
          </nav>
        </aside>
      </div>
    </Transition>
  </Teleport>

  <!-- White vertical rail (desktop) -->
  <aside class="rail hidden lg:flex fixed left-0 top-0 bottom-0 w-16 z-40">
    <nav
      class="rail-nav flex h-[calc(100%)] w-16 flex-col items-center gap-3 bg-white text-[#0d0f14]"
      aria-label="Primary"
    >
      <!-- Organization logo / fallback -->
      <Link
        class="rail-btn mt-4 flex shrink-0"
        :href="r('dashboard', { organization: org })"
        aria-label="Dashboard"
      >
        <img
          v-if="logoUrl"
          :src="logoUrl"
          alt=""
          class="h-8 w-8 rounded-lg object-contain"
        >
        <span
          v-else
          class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--primary)] text-sm font-semibold text-white"
        >
          {{ (organization?.name || 'O').charAt(0).toUpperCase() }}
        </span>
      </Link>

      <!-- Dashboard -->
      <Link
        class="rail-btn"
        :href="r('dashboard', { organization: org })"
        aria-label="Dashboard"
        :class="tileClass(isCurrent('dashboard'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path d="M3 12l9-9 9 9" />
          <path d="M9 21V12h6v9" />
        </svg>
      </Link>

      <!-- Clients -->
      <Link
        class="rail-btn"
        :href="r('clients.index', { organization: org })"
        aria-label="Clients"
        :class="tileClass(isCurrent('clients.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path d="M16 11a4 4 0 10-8 0 4 4 0 008 0z" />
          <path d="M6 21a6 6 0 0112 0" />
        </svg>
      </Link>

      <!-- Projects -->
      <Link
        class="rail-btn"
        :href="r('projects.index', { organization: org })"
        aria-label="Projects"
        :class="tileClass(isCurrent('projects.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <rect x="3" y="4" width="18" height="14" rx="2" />
          <path d="M3 8h18" />
        </svg>
      </Link>

      <!-- Tasks -->
      <Link
        class="rail-btn"
        :href="r('tasks.board', { organization: org })"
        aria-label="Tasks"
        :class="tileClass(isCurrentAny(['tasks.index', 'tasks.board']))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
        </svg>
      </Link>

      <!-- Announcements -->
      <Link
        class="rail-btn"
        :href="r('announcements.index', { organization: org })"
        aria-label="Announcements"
        :class="tileClass(isCurrent('announcements.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path d="M3 11l18-5v10l-18-5v6a3 3 0 003 3h2" />
          <path d="M9 21V9" />
        </svg>
      </Link>

      <!-- Divider -->
      <div class="h-px w-8 bg-zinc-300/30 my-2"></div>

      <!-- HRM / Employees (Admins only) -->
      <Link
        v-if="isAdmin"
        class="rail-btn"
        :href="r('hrm.index', { organization: org })"
        aria-label="Employees"
        :class="tileClass(isCurrent('hrm.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 00-3-3.87" />
          <path d="M16 3.13a4 4 0 010 7.75" />
        </svg>
      </Link>

      <!-- Activity (permission-gated) -->
      <Link
        v-if="canViewActivity"
        class="rail-btn"
        :href="r('activity.index', { organization: org })"
        aria-label="Activity"
        :class="tileClass(isCurrent('activity.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      </Link>

      <!-- Attendance -->
      <Link
        class="rail-btn"
        :href="r('attendance.index', { organization: org })"
        aria-label="Attendance"
        :class="tileClass(isCurrent('attendance.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <circle cx="12" cy="12" r="10" />
          <path d="M12 6v6l4 2" />
        </svg>
      </Link>

      <!-- Settings (Admins only) -->
      <Link
        v-if="isAdmin"
        class="rail-btn"
        :href="r('settings.index', { organization: org })"
        aria-label="Settings"
        :class="tileClass(isCurrent('settings.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <circle cx="12" cy="12" r="3" />
          <path d="M12 1v6m0 6v6M3.93 3.93l4.24 4.24m5.66 5.66l4.24 4.24M1 12h6m6 0h6M3.93 20.07l4.24-4.24m5.66-5.66l4.24-4.24" />
        </svg>
      </Link>

      <!-- Billing (Admins only) -->
      <Link
        v-if="isAdmin"
        class="rail-btn"
        :href="r('billing.index', { organization: org })"
        aria-label="Billing"
        :class="tileClass(isCurrent('billing.index'))"
      >
        <svg
          viewBox="0 0 24 24"
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
          <path d="M1 10h22" />
        </svg>
      </Link>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps<{ mobileOpen?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const mobileOpen = computed(() => props.mobileOpen ?? false)

const page = usePage<{ auth?: { is_admin?: boolean; can?: { activity?: boolean } }; organization?: { name?: string; logo_path?: string | null } }>()
const isAdmin = computed(() => !!page.props.auth?.is_admin)
const canViewActivity = computed(() => !!page.props.auth?.can?.activity)
const organization = computed(() => page.props.organization ?? null)
const logoUrl = computed(() => {
  const path = organization.value?.logo_path
  return path ? `/storage/${path}` : null
})

/* Ziggy helper */
const routeGlobal = (window as any).route

const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

/* Resolve org slug safely */
const org = computed(() => {
  try {
    const p = routeGlobal()?.params ?? {}
    if (p.organization) return p.organization
  } catch {
    // ignore
  }

  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

/* Active state: URL path matching to fix Settings highlighting on Dashboard */
function isCurrent(name: string) {
  try {
    const path = window.location.pathname
    const itemUrl = routeGlobal ? routeGlobal(name, { organization: org.value }, false) : ''
    const itemPath = typeof itemUrl === 'string' && itemUrl.startsWith('http') ? new URL(itemUrl).pathname : itemUrl

    if (!itemPath) return false

    if (name === 'dashboard') {
      return path === itemPath || path === itemPath + '/'
    }
    return path === itemPath || path.startsWith(itemPath + '/')
  } catch {
    return false
  }
}

function isCurrentAny(names: string[]) {
  return names.some((name) => isCurrent(name))
}

/* Tile classes (white pill + active ring) */
function tileClass(active: boolean) {
  return [
    'group relative grid place-items-center h-12 w-12 rounded-xl transition',
    'text-[#0d0f14]',
    active ? 'current' : '',
  ].join(' ')
}
</script>

<style scoped>
.mobile-nav-link {
  @apply block rounded-lg px-3 py-2.5 text-sm font-medium transition;
  color: rgb(13 15 20 / 0.8);
}
.mobile-nav-link:hover {
  @apply bg-gray-100;
}
.mobile-nav-link-active {
  @apply bg-indigo-100 text-indigo-600;
}
.mobile-drawer-enter-active,
.mobile-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.mobile-drawer-enter-active aside,
.mobile-drawer-leave-active aside {
  transition: transform 0.25s cubic-bezier(0.32, 0.72, 0, 1);
}
.mobile-drawer-enter-from,
.mobile-drawer-leave-to {
  opacity: 0;
}
.mobile-drawer-enter-from aside,
.mobile-drawer-leave-to aside {
  transform: translateX(-100%);
}
</style>
