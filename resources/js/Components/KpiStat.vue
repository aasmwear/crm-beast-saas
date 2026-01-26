<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  trend?: string
}>();
</script>

<template>
  <div class="rounded-xl bg-elev-2/70 border border-white/10 px-4 py-3">
    <div class="text-xs text-muted">{{ label }}</div>
    <div class="mt-1 text-lg font-semibold">{{ value }}</div>
    <div v-if="trend" class="mt-1 text-xs text-positive">{{ trend }}</div>
  </div>
</template>
