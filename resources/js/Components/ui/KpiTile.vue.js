<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  trend?: string
}>();
</script>

<template>
  <div
    class="rounded-2xl border border-white/5 bg-white/2 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] p-5 flex items-center justify-between">
    <div class="space-y-1">
      <div class="text-xs text-muted-foreground">{{ label }}</div>
      <div class="text-2xl font-semibold text-foreground">{{ value }}</div>
    </div>
    <div v-if="trend" class="text-xs px-2 py-1 rounded-full bg-emerald-500/10 text-emerald-400">
      {{ trend }}
    </div>
  </div>
</template>
