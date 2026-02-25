<script setup lang="ts">
defineProps<{
  /** Optional breadcrumb text (e.g. "Organization • ACME") */
  breadcrumb?: string
  /** Main page title (H1) */
  title: string
  /** Optional subtitle/description */
  subtitle?: string
}>()
</script>

<template>
  <section class="page-header hero-slab">
    <div class="page-header__inner flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div class="page-header__content min-w-0 flex-1">
        <p v-if="breadcrumb" class="page-header__breadcrumb text-xs text-white/60 tracking-wide">
          {{ breadcrumb }}
        </p>
        <h1 class="page-header__title mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-white">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="page-header__subtitle mt-1 text-sm text-white/60">
          {{ subtitle }}
        </p>
      </div>
      <div v-if="$slots.actions" class="page-header__actions flex items-center gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.page-header {
  padding: 1.5rem 1.75rem;
}

.page-header__inner {
  max-width: 100%;
}

@media (min-width: 640px) {
  .page-header {
    padding: 1.75rem 2rem;
  }
}
</style>
