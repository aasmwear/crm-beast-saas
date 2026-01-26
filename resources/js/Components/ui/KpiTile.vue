<script setup lang="ts">
defineProps<{ label: string; value: number | string; trend?: string; positive?: boolean }>()
</script>

<template>
  <div class="rounded-2xl px-4 py-3 border border-white/5 bg-white/5">
    <div class="text-xs text-muted-foreground">{{ label }}</div>
    <div class="mt-1 text-2xl font-semibold">{{ value }}</div>
    <div v-if="trend" class="text-xs mt-0.5" :class="positive ? 'text-success' : 'text-danger'">{{ trend }}</div>
  </div>
</template>
