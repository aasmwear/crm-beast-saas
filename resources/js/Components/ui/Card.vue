<script setup lang="ts">
defineProps<{ class?: string }>()
</script>

<template>
  <div :class="['rounded-3xl border border-[#3A3A42] bg-[#1A1B22]/90 shadow-[0_8px_30px_rgba(0,0,0,.35)]', $props.class]">
    <slot />
  </div>
</template>
