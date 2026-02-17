<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface NotificationItem {
  id: string
  type: string
  message: string
  url: string | null
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

const open = ref(false)
const notifications = ref<NotificationItem[]>([])
const unreadCount = ref(0)
const loading = ref(false)

const routeGlobal = (window as any).route
const org = computed(() => {
  const p = routeGlobal?.()?.params ?? {}
  if (p.organization) return p.organization
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

const listUrl = computed(() => {
  if (!routeGlobal) return ''
  return routeGlobal('notifications.list', { organization: org.value })
})

const csrfToken = () =>
  (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

function fetchList() {
  if (!listUrl.value) return
  loading.value = true
  fetch(listUrl.value, { credentials: 'include', headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .then((data: { notifications?: NotificationItem[]; unread_count?: number }) => {
      notifications.value = data.notifications ?? []
      unreadCount.value = data.unread_count ?? 0
    })
    .catch(() => {})
    .finally(() => { loading.value = false })
}

function markRead(id: string) {
  const url = routeGlobal?.('notifications.read', { organization: org.value, notification: id })
  if (!url) return
  fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  }).then(() => {
    fetchList()
  })
}

function markAllRead() {
  const url = routeGlobal?.('notifications.readAll', { organization: org.value })
  if (!url) return
  fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  }).then(() => {
    fetchList()
  })
}

function onNotificationClick(n: NotificationItem) {
  markRead(n.id)
  open.value = false
  if (n.url) {
    router.visit(n.url)
  }
}

function formatTime(iso: string) {
  try {
    const d = new Date(iso)
    const now = new Date()
    const diff = (now.getTime() - d.getTime()) / 60000
    if (diff < 1) return 'Just now'
    if (diff < 60) return `${Math.floor(diff)}m ago`
    if (diff < 1440) return `${Math.floor(diff / 60)}h ago`
    return d.toLocaleDateString()
  } catch {
    return ''
  }
}

let pollTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchList()
  pollTimer = setInterval(fetchList, 30000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="relative notif-btn p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition"
      aria-label="Notifications"
      @click="open = !open"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <path d="M14 17h-8a2 2 0 002 2h4a2 2 0 002-2zM18 16v-5a6 6 0 10-12 0v5l-2 2h16l-2-2z" />
      </svg>
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold text-white bg-red-500 rounded-full"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-full mt-2 w-[360px] max-h-[400px] flex flex-col rounded-xl bg-gray-900 border border-gray-700 shadow-xl z-50"
    >
      <div class="p-3 border-b border-gray-700 flex items-center justify-between">
        <span class="text-sm font-semibold text-white">Notifications</span>
        <button
          v-if="unreadCount > 0"
          type="button"
          class="text-xs text-indigo-400 hover:text-indigo-300"
          @click="markAllRead"
        >
          Mark all as read
        </button>
      </div>

      <div class="overflow-y-auto flex-1">
        <div v-if="loading && notifications.length === 0" class="p-6 text-center text-white/50 text-sm">
          Loading…
        </div>
        <div
          v-else-if="notifications.length === 0"
          class="p-6 text-center text-white/50 text-sm"
        >
          No new notifications.
        </div>
        <button
          v-for="n in notifications"
          :key="n.id"
          type="button"
          class="w-full text-left flex items-start gap-3 p-3 hover:bg-gray-800/80 transition border-b border-gray-800 last:border-0"
          @click="onNotificationClick(n)"
        >
          <span class="flex-shrink-0 w-9 h-9 rounded-full bg-indigo-600/30 flex items-center justify-center text-indigo-300 text-sm font-medium">
            {{ n.type === 'task_assigned' ? 'T' : 'P' }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm text-white/90 line-clamp-2">{{ n.message }}</p>
            <p class="text-xs text-white/50 mt-0.5">{{ formatTime(n.created_at) }}</p>
          </div>
        </button>
      </div>
    </div>

    <div
      v-if="open"
      class="fixed inset-0 z-40"
      aria-hidden="true"
      @click="open = false"
    />
  </div>
</template>
