<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>();
</script>

<template>
  <section class="relative gradient-hero noise-hero rounded-3xl p-6 md:p-8 text-white overflow-hidden">
    <div class="flex items-start gap-4">
      <div class="flex-1">
        <h2 class="text-2xl md:text-3xl font-semibold">{{ title }}</h2>
        <p v-if="subtitle" class="mt-2 text-white/80 text-sm">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-3">
        <slot name="actions" />
      </div>
    </div>

    <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
      <slot name="metrics" />
    </div>
  </section>
</template>
