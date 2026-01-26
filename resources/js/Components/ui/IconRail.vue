<!-- resources/js/Components/ui/IconRail.vue -->
<template>
  <!-- White vertical rail -->
  <aside class="rail hidden lg:flex fixed left-0 top-0 bottom-0 w-16 z-40">
    <nav
      class="rail-nav flex h-[calc(100%)] w-16 flex-col items-center gap-3 bg-white text-[#0d0f14]"
      aria-label="Primary"
    >
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
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

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

/* Active state via Ziggy */
function isCurrent(name: string) {
  try {
    return !!routeGlobal()?.current?.(name)
  } catch {
    return false
  }
}

function isCurrentAny(names: string[]) {
  try {
    const current = routeGlobal()?.current
    if (typeof current !== 'function') return false
    return names.some((name) => !!current(name))
  } catch {
    return false
  }
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
