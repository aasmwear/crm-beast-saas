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
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-sm"
          aria-hidden="true"
          @click="emit('close')"
        />
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
            <Link :href="r('dashboard', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('dashboard') }" @click="emit('close')">Dashboard</Link>
            <Link :href="r('clients.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('clients.index', 'clients.show', 'clients.create', 'clients.edit') }" @click="emit('close')">Clients</Link>
            <Link :href="r('projects.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('projects.index', 'projects.show', 'projects.create', 'projects.edit') }" @click="emit('close')">Projects</Link>
            <Link :href="r('tasks.board', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('tasks.board', 'tasks.index') }" @click="emit('close')">Tasks</Link>
            <Link :href="r('announcements.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('announcements.index') }" @click="emit('close')">Announcements</Link>
            <div v-if="isAdmin" class="border-t border-[#0d0f14]/10 my-2 pt-2">
              <Link v-if="canViewActivity" :href="r('activity.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('activity.index') }" @click="emit('close')">Activity</Link>
              <Link v-if="canViewHrm" :href="r('hrm.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('hrm.index') }" @click="emit('close')">Employees</Link>
              <Link v-if="canViewAttendance" :href="r('attendance.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('attendance.index') }" @click="emit('close')">Attendance</Link>
              <Link :href="r('settings.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('settings.index') }" @click="emit('close')">Settings</Link>
              <Link :href="r('billing.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('billing.index', 'billing.portal', 'billing.checkout') }" @click="emit('close')">Billing</Link>
            </div>
            <div v-else class="border-t border-[#0d0f14]/10 my-2 pt-2">
              <Link v-if="canViewActivity" :href="r('activity.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('activity.index') }" @click="emit('close')">Activity</Link>
              <Link v-if="canViewHrm" :href="r('hrm.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('hrm.index') }" @click="emit('close')">Employees</Link>
              <Link v-if="canViewAttendance" :href="r('attendance.index', { organization: org })" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': isActive('attendance.index') }" @click="emit('close')">Attendance</Link>
            </div>
            <div class="mt-auto border-t border-[#0d0f14]/10 pt-2">
              <Link :href="profileHref" class="mobile-nav-link" @click="emit('close')">Profile</Link>
              <button type="button" class="mobile-nav-link w-full text-left text-red-600" @click="handleLogout">Sign out</button>
            </div>
          </nav>
        </aside>
      </div>
    </Transition>
  </Teleport>

  <!-- Profile dropdown (body-teleported so overflow:hidden on rail doesn't clip it) -->
  <Teleport to="body">
    <Transition name="dropdown-fade">
      <div
        v-if="profileOpen"
        ref="dropdownRef"
        class="profile-dropdown"
        :style="dropdownStyle"
        @click.stop
      >
        <Link :href="profileHref" class="profile-item" @click="profileOpen = false">
          <svg class="profile-item__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 20a8 8 0 0116 0" />
          </svg>
          Profile
        </Link>
        <Link
          v-if="isAdmin"
          :href="r('settings.index', { organization: org })"
          class="profile-item"
          @click="profileOpen = false"
        >
          <svg class="profile-item__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <circle cx="12" cy="12" r="3" />
            <path d="M12 1v4m0 14v4M4.22 4.22l2.83 2.83m9.9 9.9 2.83 2.83M1 12h4m14 0h4M4.22 19.78l2.83-2.83m9.9-9.9 2.83-2.83" />
          </svg>
          Settings
        </Link>
        <div class="profile-divider" />
        <button type="button" class="profile-item profile-item--danger" @click="handleLogout">
          <svg class="profile-item__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          Sign out
        </button>
      </div>
    </Transition>
  </Teleport>

  <!-- White vertical rail (desktop) -->
  <aside
    class="rail hidden lg:flex fixed left-0 top-0 bottom-0 z-40"
    aria-label="Primary navigation"
  >
    <nav
      class="rail-nav flex h-full flex-col bg-white text-[#0d0f14]"
      aria-label="Primary"
    >
      <!-- Organization logo / brand -->
      <Link
        class="rail-brand"
        :href="r('dashboard', { organization: org })"
        aria-label="Go to dashboard"
      >
        <img
          v-if="logoUrl"
          :src="logoUrl"
          alt=""
          class="h-8 w-8 rounded-lg object-contain"
        >
        <span v-else class="org-initial">
          {{ (organization?.name || 'O').charAt(0).toUpperCase() }}
        </span>
      </Link>

      <!-- Main nav items -->
      <div class="rail-nav__scroll flex flex-col gap-1 mt-1 overflow-hidden">

        <!-- Dashboard -->
        <Link
          class="rail-btn"
          :class="{ current: isActive('dashboard') }"
          :href="r('dashboard', { organization: org })"
          :aria-current="isActive('dashboard') ? 'page' : undefined"
          aria-label="Dashboard"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M3 12l9-9 9 9" />
              <path d="M9 21V12h6v9" />
            </svg>
          </span>
          <span class="rail-btn__label">Dashboard</span>
        </Link>

        <!-- Clients -->
        <Link
          class="rail-btn"
          :class="{ current: isActive('clients.index', 'clients.show', 'clients.create', 'clients.edit') }"
          :href="r('clients.index', { organization: org })"
          :aria-current="isActive('clients.index', 'clients.show', 'clients.create', 'clients.edit') ? 'page' : undefined"
          aria-label="Clients"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M16 11a4 4 0 10-8 0 4 4 0 008 0z" />
              <path d="M6 21a6 6 0 0112 0" />
            </svg>
          </span>
          <span class="rail-btn__label">Clients</span>
        </Link>

        <!-- Projects -->
        <Link
          class="rail-btn"
          :class="{ current: isActive('projects.index', 'projects.show', 'projects.create', 'projects.edit') }"
          :href="r('projects.index', { organization: org })"
          :aria-current="isActive('projects.index', 'projects.show', 'projects.create', 'projects.edit') ? 'page' : undefined"
          aria-label="Projects"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <rect x="3" y="4" width="18" height="14" rx="2" />
              <path d="M3 8h18" />
            </svg>
          </span>
          <span class="rail-btn__label">Projects</span>
        </Link>

        <!-- Tasks -->
        <Link
          class="rail-btn"
          :class="{ current: isActive('tasks.board', 'tasks.index') }"
          :href="r('tasks.board', { organization: org })"
          :aria-current="isActive('tasks.board', 'tasks.index') ? 'page' : undefined"
          aria-label="Tasks"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M9 11l3 3L22 4" />
              <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
          </span>
          <span class="rail-btn__label">Tasks</span>
        </Link>

        <!-- Announcements -->
        <Link
          class="rail-btn"
          :class="{ current: isActive('announcements.index') }"
          :href="r('announcements.index', { organization: org })"
          :aria-current="isActive('announcements.index') ? 'page' : undefined"
          aria-label="Announcements"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M3 11l18-5v10l-18-5v6a3 3 0 003 3h2" />
              <path d="M9 21V9" />
            </svg>
          </span>
          <span class="rail-btn__label">Announcements</span>
        </Link>

        <!-- Section divider: General → Admin/Shared -->
        <div class="rail-section-sep" aria-hidden="true"></div>

        <!-- HRM / Employees (permission-gated) -->
        <Link
          v-if="canViewHrm"
          class="rail-btn"
          :class="{ current: isActive('hrm.index') }"
          :href="r('hrm.index', { organization: org })"
          :aria-current="isActive('hrm.index') ? 'page' : undefined"
          aria-label="Employees"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 00-3-3.87" />
              <path d="M16 3.13a4 4 0 010 7.75" />
            </svg>
          </span>
          <span class="rail-btn__label">Employees</span>
        </Link>

        <!-- Activity (permission-gated) -->
        <Link
          v-if="canViewActivity"
          class="rail-btn"
          :class="{ current: isActive('activity.index') }"
          :href="r('activity.index', { organization: org })"
          :aria-current="isActive('activity.index') ? 'page' : undefined"
          aria-label="Activity"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </span>
          <span class="rail-btn__label">Activity</span>
        </Link>

        <!-- Attendance (permission-gated) -->
        <Link
          v-if="canViewAttendance"
          class="rail-btn"
          :class="{ current: isActive('attendance.index') }"
          :href="r('attendance.index', { organization: org })"
          :aria-current="isActive('attendance.index') ? 'page' : undefined"
          aria-label="Attendance"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" />
            </svg>
          </span>
          <span class="rail-btn__label">Attendance</span>
        </Link>

        <!-- Settings (Admins only) -->
        <Link
          v-if="isAdmin"
          class="rail-btn"
          :class="{ current: isActive('settings.index') }"
          :href="r('settings.index', { organization: org })"
          :aria-current="isActive('settings.index') ? 'page' : undefined"
          aria-label="Settings"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <circle cx="12" cy="12" r="3" />
              <path d="M12 1v4m0 14v4M4.22 4.22l2.83 2.83m9.9 9.9 2.83 2.83M1 12h4m14 0h4M4.22 19.78l2.83-2.83m9.9-9.9 2.83-2.83" />
            </svg>
          </span>
          <span class="rail-btn__label">Settings</span>
        </Link>

        <!-- Billing (Admins only) -->
        <Link
          v-if="isAdmin"
          class="rail-btn"
          :class="{ current: isActive('billing.index', 'billing.portal', 'billing.checkout') }"
          :href="r('billing.index', { organization: org })"
          :aria-current="isActive('billing.index', 'billing.portal', 'billing.checkout') ? 'page' : undefined"
          aria-label="Billing"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
              <path d="M1 10h22" />
            </svg>
          </span>
          <span class="rail-btn__label">Billing</span>
        </Link>
      </div>

      <!-- Profile / Auth area -->
      <div class="rail-profile mt-auto pb-2" ref="profileAreaRef">
        <div class="rail-section-sep" aria-hidden="true"></div>
        <button
          ref="profileBtnRef"
          type="button"
          class="rail-btn rail-btn--profile"
          :aria-expanded="profileOpen"
          aria-label="User menu"
          @click="toggleProfile"
        >
          <span class="rail-btn__icon" aria-hidden="true">
            <span class="avatar-circle" :title="userName">{{ initials }}</span>
          </span>
          <span class="rail-btn__label">{{ userName }}</span>
        </button>
      </div>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const props = defineProps<{ mobileOpen?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const mobileOpen = computed(() => props.mobileOpen ?? false)

/* ─── Page / auth data ──────────────────────────────────────── */
const page = usePage<{
  auth?: {
    is_admin?: boolean
    can?: { activity?: boolean; reports?: boolean; attendance?: boolean; hrm?: boolean }
    user?: { name?: string; email?: string }
  }
  organization?: { name?: string; logo_path?: string | null }
}>()

const isAdmin = computed(() => !!page.props.auth?.is_admin)
const canViewActivity = computed(() => !!page.props.auth?.can?.activity)
const canViewAttendance = computed(() => !!page.props.auth?.can?.attendance)
const canViewHrm = computed(() => !!page.props.auth?.can?.hrm)
const organization = computed(() => page.props.organization ?? null)
const logoUrl = computed(() => {
  const path = organization.value?.logo_path
  return path ? `/storage/${path}` : null
})
const userName = computed(() => (page.props.auth?.user as any)?.name ?? 'Account')
const initials = computed(() => {
  const n = String(userName.value).trim()
  return n.split(/\s+/).map((s: string) => s[0] ?? '').join('').slice(0, 2).toUpperCase() || 'U'
})

/* ─── Ziggy / org helpers ───────────────────────────────────── */
const routeGlobal = (window as any).route

const r = (name: string, params: any = {}, absolute = false) =>
  routeGlobal ? routeGlobal(name, params, absolute) : '#'
const logoutHref = computed(() => (routeGlobal ? r('logout') : '/logout'))
const profileHref = computed(() => (routeGlobal ? r('profile.edit') : '/profile'))

const org = computed(() => {
  try {
    const p = routeGlobal()?.params ?? {}
    if (p.organization) return p.organization
  } catch { /* ignore */ }
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

/* ─── Active-state detection ────────────────────────────────────
 * Uses page.url (Inertia reactive) so highlighting updates on SPA
 * navigation even though AuthenticatedLayout is a persistent layout.
 * Falls back to window.location for first paint resilience.
 * isActive(...routeNames) — matches the current URL against any of
 * the listed named routes; nested routes (show/create/edit) are
 * included by passing them explicitly.
 * ──────────────────────────────────────────────────────────────── */
function resolvePath(name: string): string {
  try {
    const url = routeGlobal ? routeGlobal(name, { organization: org.value }, false) : ''
    if (typeof url !== 'string' || !url) return ''
    return (url.startsWith('http') ? new URL(url).pathname : url).replace(/\/$/, '')
  } catch {
    return ''
  }
}

function isActive(...routeNames: string[]): boolean {
  // Preferred check: Ziggy route().current(...) when available.
  try {
    const ziggyRouter = routeGlobal?.()
    if (ziggyRouter && typeof ziggyRouter.current === 'function') {
      const matched = routeNames.some((name) => ziggyRouter.current(name))
      if (matched) return true
    }
  } catch {
    // fall through to path-based fallback
  }

  // Use page.url for reactivity on Inertia navigation.
  const rawUrl: string = (page.url as string) || window.location.pathname
  const currentPath = rawUrl.split('?')[0].replace(/\/$/, '') || '/'

  return routeNames.some(name => {
    const itemPath = resolvePath(name)
    if (!itemPath) return false
    // Exact match OR the current path is a child (starts with itemPath + /)
    return currentPath === itemPath || currentPath.startsWith(itemPath + '/')
  })
}

/* ─── Profile dropdown ──────────────────────────────────────── */
const profileOpen = ref(false)
const profileBtnRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

function toggleProfile() {
  if (!profileOpen.value) {
    // Keep horizontal anchor stable relative to collapsed rail width.
    const rect = profileBtnRef.value?.getBoundingClientRect()
    if (rect) {
      const collapsedRailWidth = 76
      dropdownStyle.value = {
        position: 'fixed',
        left: `${collapsedRailWidth + 10}px`,
        bottom: `${window.innerHeight - rect.bottom}px`,
        zIndex: '200',
      }
    }
  }
  profileOpen.value = !profileOpen.value
}

function closeProfileOnOutsideClick(e: MouseEvent) {
  const target = e.target as Node
  if (
    profileOpen.value &&
    !profileBtnRef.value?.contains(target) &&
    !dropdownRef.value?.contains(target)
  ) {
    profileOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', closeProfileOnOutsideClick, true))
onUnmounted(() => document.removeEventListener('click', closeProfileOnOutsideClick, true))

/* ─── Logout ─────────────────────────────────────────────────── */
function handleLogout() {
  profileOpen.value = false
  emit('close')
  router.post(logoutHref.value)
}
</script>

<style scoped>
/* ─── Mobile nav ────────────────────────────────────────────── */
.mobile-nav-link {
  @apply block rounded-lg px-3 py-2.5 text-sm font-medium transition;
  color: rgb(13 15 20 / 0.8);
}
.mobile-nav-link:hover { @apply bg-gray-100; }
.mobile-nav-link-active { @apply bg-indigo-100 text-indigo-600; }

.mobile-drawer-enter-active,
.mobile-drawer-leave-active { transition: opacity 0.2s ease; }
.mobile-drawer-enter-active aside,
.mobile-drawer-leave-active aside { transition: transform 0.25s cubic-bezier(0.32, 0.72, 0, 1); }
.mobile-drawer-enter-from,
.mobile-drawer-leave-to { opacity: 0; }
.mobile-drawer-enter-from aside,
.mobile-drawer-leave-to aside { transform: translateX(-100%); }

/* ─── Desktop rail ──────────────────────────────────────────── */
.rail {
  width: var(--rail-width, 76px);
  overflow: hidden;
  transition: width 240ms cubic-bezier(0.32, 0.72, 0, 1),
              box-shadow 240ms ease;
}
.rail:hover,
.rail:focus-within {
  width: 216px;
  box-shadow: 2px 0 28px rgba(0, 0, 0, 0.13);
}

.rail-nav {
  width: 100%;
  padding: 0;
  align-items: stretch;
}

/* The scroll area clips label overflow during width transition */
.rail-nav__scroll {
  overflow-x: clip;
  overflow-y: auto;
  flex-shrink: 0;
}
@supports not (overflow: clip) {
  .rail-nav__scroll {
    overflow-x: hidden;
  }
}

/* ─── Brand/logo ─────────────────────────────────────────────── */
.rail-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 64px;
  flex-shrink: 0;
  align-self: center;
  width: calc(var(--rail-width, 76px) - 16px);
  text-decoration: none;
}
.org-initial {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--primary, #684EF4);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
}

/* ─── Nav buttons — pill style ───────────────────────────────── */
.rail-btn {
  /* Pill inset: 6px left/right margin creates the pill-within-rail look */
  display: flex;
  align-items: center;
  margin: 0 6px;
  /* Height for click target; label area fills horizontally */
  height: 2.75rem;
  /* Border-radius gives the premium pill feel */
  border-radius: 0.75rem;
  overflow: hidden;
  text-decoration: none;
  color: #0d0f14;
  cursor: pointer;
  position: relative;
  width: calc(100% - 12px);
  justify-content: center;
  gap: 0.75rem;
  transition: background 0.18s ease,
              color 0.18s ease,
              box-shadow 0.18s ease;
  /* Suppress translateY from global theme.css .rail-btn:hover */
  transform: none !important;
  border: none;
  box-shadow: none;
  background: transparent;
  padding: 0;
  gap: 0;
}

/* Hover (inactive) */
.rail-btn:hover:not(.current) {
  background: linear-gradient(135deg, #0a0f3d 0%, #2a3adb 100%);
  color: #fff;
}

/* Active/current — pill with gradient + glow */
.rail-btn.current {
  background: linear-gradient(135deg, #00054e 0%, #3e53ff 100%);
  color: #fff;
  box-shadow: 0 2px 14px rgba(62, 83, 255, 0.38),
              0 0 0 1px rgba(62, 83, 255, 0.18);
}

/* Focus-visible ring */
.rail-btn:focus-visible {
  outline: 2px solid rgba(139, 92, 246, 0.75);
  outline-offset: 1px;
}

/* Profile button variant — slightly different hover */
.rail-btn--profile:hover:not(.current) {
  background: rgba(13, 15, 20, 0.06);
  color: #0d0f14;
}

/* ─── Icon column: fixed 44px, always centred ────────────────── */
.rail-btn__icon {
  flex-shrink: 0;
  width: 1.5rem;
  height: 2.75rem;
  display: grid;
  place-items: center;
}

/* When rail expands, icon aligns to the left (natural text flow) */
.rail:hover .rail-btn,
.rail:focus-within .rail-btn {
  justify-content: flex-start;
  padding-left: 0.75rem;
  gap: 0.75rem;
}

/* ─── Label — animate in on expansion ───────────────────────── */
.rail-btn__label {
  flex: 0 1 auto;
  max-width: 0;
  font-size: 0.8125rem;
  font-weight: 500;
  letter-spacing: 0.015em;
  white-space: nowrap;
  padding-right: 0;
  opacity: 0;
  transform: translateX(-6px);
  transition: opacity 160ms 80ms ease, transform 180ms 60ms ease, max-width 200ms ease;
  pointer-events: none;
  color: inherit;
  overflow: hidden;
}
.rail:hover .rail-btn__label,
.rail:focus-within .rail-btn__label {
  max-width: 120px;
  opacity: 1;
  transform: translateX(0);
  padding-right: 12px;
}

/* ─── Avatar circle ──────────────────────────────────────────── */
.avatar-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3e53ff, #8b5cf6);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  flex-shrink: 0;
  user-select: none;
}

/* ─── Section separator (replaces old rail-sep) ──────────────── */
.rail-section-sep {
  height: 1px;
  background: rgba(13, 15, 20, 0.08);
  /* Align with button pills: same 6px side margin + icon left edge */
  margin: 6px 12px;
  flex-shrink: 0;
}

/* ─── Profile dropdown ──────────────────────────────────────── */
.profile-dropdown {
  min-width: 176px;
  background: #fff;
  border: 1px solid rgba(13, 15, 20, 0.08);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.16), 0 2px 8px rgba(0, 0, 0, 0.08);
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.profile-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0.5rem 0.625rem;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #0d0f14;
  text-decoration: none;
  cursor: pointer;
  background: transparent;
  border: none;
  width: 100%;
  text-align: left;
  transition: background 0.15s ease;
}
.profile-item:hover { background: rgba(13, 15, 20, 0.06); }
.profile-item--danger { color: #dc2626; }
.profile-item--danger:hover { background: rgba(220, 38, 38, 0.08); }

.profile-item__icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  opacity: 0.75;
}

.profile-divider {
  height: 1px;
  background: rgba(13, 15, 20, 0.08);
  margin: 3px 4px;
}

/* Dropdown enter/leave */
.dropdown-fade-enter-active { transition: opacity 120ms ease, transform 140ms ease; }
.dropdown-fade-leave-active { transition: opacity 80ms ease; }
.dropdown-fade-enter-from { opacity: 0; transform: translateY(4px); }
.dropdown-fade-leave-to { opacity: 0; }
</style>
