<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  hint?: string
}>();
</script>

<template>
  <div class="glass-card rounded-2xl px-4 py-3 tile-active text-foreground/90">
    <div class="text-xs text-white/70">{{ label }}</div>
    <div class="mt-1 text-lg font-semibold text-white">{{ value }}</div>
    <div v-if="hint" class="text-xs text-emerald-300/90 mt-0.5">{{ hint }}</div>
  </div>
</template>
