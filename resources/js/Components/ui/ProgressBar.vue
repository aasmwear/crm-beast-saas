<script setup lang="ts">
defineProps<{
  value: number
  max?: number
  class?: string
}>()
</script>

<template>
  <div
    class="h-2 w-full overflow-hidden rounded-full bg-white/10"
    :class="$props.class"
    role="progressbar"
    :aria-valuenow="value"
    :aria-valuemin="0"
    :aria-valuemax="max ?? 100"
  >
    <div
      class="h-full rounded-full bg-gradient-to-r from-[var(--primary)] to-violet-500 transition-all duration-500 ease-out"
      :style="{ width: `${Math.min(100, Math.max(0, (value / (max ?? 100)) * 100))}%` }"
    />
  </div>
</template>
