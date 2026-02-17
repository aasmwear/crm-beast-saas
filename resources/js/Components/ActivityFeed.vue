<script setup lang="ts">
type ActivityItem = {
  id: number
  description: string
  properties: Record<string, unknown> | null
  created_at: string | null
  user: { id: number; name: string } | null
}

defineProps<{
  activities: ActivityItem[]
}>()

function formatTime(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins} minutes ago`
  if (diffHours < 24) return `${diffHours} hours ago`
  if (diffDays < 7) return `${diffDays} days ago`
  return d.toLocaleDateString()
}

function activityText(a: ActivityItem): string {
  const userName = a.user?.name ?? 'Someone'
  return `${userName} ${a.description}`
}
</script>

<template>
  <div class="space-y-4">
    <h3 class="text-sm font-semibold text-white">
      Activity
    </h3>

    <div v-if="activities.length > 0" class="relative space-y-0">
      <!-- Vertical timeline -->
      <div
        v-for="(a, idx) in activities"
        :key="a.id"
        class="relative flex gap-3 pb-4"
      >
        <!-- Timeline dot + line -->
        <div class="relative flex shrink-0 flex-col items-center">
          <div class="h-2.5 w-2.5 rounded-full bg-indigo-500/70" />
          <div
            v-if="idx < activities.length - 1"
            class="absolute top-3 left-1/2 mt-0.5 h-full w-0.5 -translate-x-1/2 bg-white/10"
          />
        </div>

        <!-- Content -->
        <div class="min-w-0 flex-1 pb-1">
          <p class="text-xs text-white/90">
            {{ activityText(a) }}
          </p>
          <p class="mt-0.5 text-[11px] text-white/50">
            {{ formatTime(a.created_at) }}
          </p>
        </div>
      </div>
    </div>
    <p v-else class="text-xs text-white/50">
      No activity yet.
    </p>
  </div>
</template>
