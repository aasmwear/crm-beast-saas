<script setup lang="ts">
defineProps<{
  title: string
  description: string
  icon?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-white/10 bg-white/5 py-16 px-8 text-center">
    <div
      class="mb-4 flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/5 text-4xl text-white/40"
      aria-hidden
    >
      <slot name="icon">
        <span v-if="icon" class="text-4xl">{{ icon }}</span>
        <svg
          v-else
          class="mx-auto h-12 w-12 text-white/30"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.5"
            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.5m-2.5 0h-5m5 0v-5a2 2 0 00-2-2H6a2 2 0 00-2 2v5m5-.75h.01"
          />
        </svg>
      </slot>
    </div>
    <h3 class="text-lg font-semibold text-white">
      {{ title }}
    </h3>
    <p class="mt-1 max-w-sm text-sm text-white/50">
      {{ description }}
    </p>
    <div v-if="$slots.action" class="mt-6">
      <slot name="action" />
    </div>
  </div>
</template>
