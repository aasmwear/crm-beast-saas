<script setup lang="ts">
defineProps<{ tight?: boolean; title?: string }>();
</script>

<template>
  <section
    class="glass-card rounded-2xl"
    :class="tight ? 'p-4' : 'p-6 md:p-8'"
  >
    <header v-if="title" class="mb-4 text-sm text-muted-foreground">
      {{ title }}
    </header>
    <slot />
  </section>
</template>
