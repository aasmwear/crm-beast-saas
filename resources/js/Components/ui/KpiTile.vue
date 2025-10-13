<script setup lang="ts">
defineProps<{ label: string; value: number | string; trend?: string }>()
</script>

<template>
  <div class="rounded-2xl px-4 py-3 border border-[#3A3A42] bg-[#20222A]">
    <div class="text-xs text-[#A9ABB8]">{{ label }}</div>
    <div class="mt-1 text-2xl font-semibold">{{ value }}</div>
    <div v-if="trend" class="text-xs text-green-400 mt-0.5">{{ trend }}</div>
  </div>
</template>
