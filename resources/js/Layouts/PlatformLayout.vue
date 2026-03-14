<template>
  <div class="min-h-screen bg-slate-950 text-white">
    <!-- Platform Sidebar (Distinct from tenant white rail) -->
    <aside class="platform-sidebar hidden lg:flex fixed left-0 top-0 bottom-0 w-64 z-40">
      <nav class="sidebar-nav flex h-full w-full flex-col bg-slate-950/95 backdrop-blur-xl border-r border-white/10">
        <!-- Logo/Branding -->
        <div class="px-6 py-8 border-b border-white/10">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
              </svg>
            </div>
            <div>
              <div class="text-sm font-bold text-white">Platform</div>
              <div class="text-xs text-purple-400">Super Admin</div>
            </div>
          </div>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 px-4 py-6 space-y-2">
          <Link
            :href="route('platform.dashboard')"
            class="platform-nav-item"
            :class="{ 'active': isCurrent('platform.dashboard') }"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="14" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
            <span>Dashboard</span>
          </Link>

          <Link
            :href="route('platform.organizations.subscriptions')"
            class="platform-nav-item"
            :class="{ 'active': isCurrent('platform.organizations.subscriptions') }"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87"/>
              <path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            <span>Org Subscriptions</span>
          </Link>

          <Link
            :href="route('platform.revenue')"
            class="platform-nav-item"
            :class="{ 'active': isCurrent('platform.revenue') }"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
            </svg>
            <span>Revenue</span>
          </Link>

          <Link
            :href="route('platform.organizations.health')"
            class="platform-nav-item"
            :class="{ 'active': isCurrent('platform.organizations.health') }"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
            <span>Org Health</span>
          </Link>

          <Link
            :href="route('platform.feature-usage')"
            class="platform-nav-item"
            :class="{ 'active': isCurrent('platform.feature-usage') }"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 20V10M12 20V4M6 20v-6"/>
            </svg>
            <span>Feature Usage</span>
          </Link>

          <div class="pt-4 mt-4 border-t border-white/10">
            <Link
              href="#"
              class="platform-nav-item"
            >
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M12 1v6m0 6v6m8-9h-6m-6 0H2"/>
              </svg>
              <span>Settings</span>
            </Link>
          </div>
        </div>

        <!-- User Section (Bottom) -->
        <div class="px-4 py-6 border-t border-white/10">
          <div class="platform-user-card">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-600 to-pink-600 flex items-center justify-center text-sm font-bold">
                {{ initials }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-white truncate">{{ user.name }}</div>
                <div class="text-xs text-white/50 truncate">{{ user.email }}</div>
              </div>
              <button 
                @click="toggleDropdown" 
                class="text-white/60 hover:text-white transition"
              >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="1"/>
                  <circle cx="12" cy="5" r="1"/>
                  <circle cx="12" cy="19" r="1"/>
                </svg>
              </button>
            </div>
            
            <!-- Dropdown -->
            <div v-if="dropdownOpen" class="absolute bottom-20 left-4 right-4 bg-slate-900/95 backdrop-blur-xl border border-white/10 rounded-lg shadow-xl">
              <button
                @click="logout"
                class="w-full px-4 py-3 text-left text-sm text-red-400 hover:bg-white/5 rounded-lg transition flex items-center gap-2"
              >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                  <polyline points="16 17 21 12 16 7"/>
                  <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Log Out
              </button>
            </div>
          </div>
        </div>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <div class="lg:pl-64">
      <!-- Header -->
      <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur-xl border-b border-white/10">
        <div class="h-16 px-6 flex items-center justify-between">
          <!-- Breadcrumb / Title -->
          <div class="flex items-center gap-3">
            <div class="px-3 py-1 rounded-full bg-purple-600/20 border border-purple-500/30 text-purple-400 text-xs font-bold">
              PLATFORM SUPER ADMIN
            </div>
            <span class="text-white/40">/</span>
            <span class="text-white/80 font-medium">{{ pageTitle }}</span>
          </div>

          <!-- Header Actions -->
          <div class="flex items-center gap-3">
            <!-- Search -->
            <div class="search-pill">
              <svg class="search-icon" viewBox="0 0 24 24" fill="none">
                <path d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <input type="text" placeholder="Search platform..." />
            </div>

            <!-- Notifications -->
            <button class="notif-btn relative">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M14 17h-8a2 2 0 002 2h4a2 2 0 002-2zM18 16v-5a6 6 0 10-12 0v5l-2 2h16l-2-2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <span class="notif-dot"></span>
            </button>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="min-h-[calc(100vh-4rem)]">
        <div class="bg-background">
          <div class="bg-background-anima">
            <div class="bg-orb-A"></div>
            <div class="bg-orb-B"></div>
            <div class="bg-orb-C"></div>
          </div>
          
          <div class="relative px-6 py-8">
            <slot />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

// Get current route name
const route = (window as any).route
const currentRoute = computed(() => {
  try {
    return route().current()
  } catch {
    return ''
  }
})

// Check if route is current
const isCurrent = (name: string) => {
  try {
    return route().current(name)
  } catch {
    return false
  }
}

// Get user from page props
const user = computed<any>(() => (usePage().props as any)?.auth?.user ?? {})

// User initials
const initials = computed(() => {
  const n = String(user.value?.name ?? 'PA').trim()
  return n.split(/\s+/).map(s => s[0]).join('').slice(0, 2).toUpperCase()
})

// Page title
const pageTitle = computed(() => {
  const component = usePage().component as string
  const parts = component.split('/')
  return parts[parts.length - 1] || 'Dashboard'
})

// Dropdown state
const dropdownOpen = ref(false)
const toggleDropdown = () => {
  dropdownOpen.value = !dropdownOpen.value
}

// Logout
const logout = () => {
  router.post(route('platform.logout'))
}
</script>

<style scoped>
/* Platform Navigation Item */
.platform-nav-item {
  @apply flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/5 transition-all duration-200;
}

.platform-nav-item.active {
  @apply bg-gradient-to-r from-purple-600/20 to-blue-600/20 text-white border border-purple-500/30;
}

/* Platform User Card */
.platform-user-card {
  @apply relative p-3 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition;
}

/* Background orbs (matching tenant app) */
.bg-background-anima {
  @apply absolute inset-0 overflow-hidden pointer-events-none;
}

.bg-orb-A, .bg-orb-B, .bg-orb-C {
  @apply absolute rounded-full blur-3xl opacity-20;
}

.bg-orb-A {
  @apply w-96 h-96 bg-purple-600 -top-48 -left-48;
}

.bg-orb-B {
  @apply w-96 h-96 bg-blue-600 top-1/3 -right-48;
}

.bg-orb-C {
  @apply w-80 h-80 bg-pink-600 bottom-0 left-1/3;
}
</style>
