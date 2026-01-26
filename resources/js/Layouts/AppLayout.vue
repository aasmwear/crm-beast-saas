<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import UserMenu from '@/Components/UserMenu.vue'

const page = usePage()
const org =
  (page.props as any).tenant?.slug ??
  (page.props as any).organization?.slug ??
  'acme'
</script>

<template>
  <div class="min-h-screen bg-[#0b0b0f] text-zinc-100">
    <!-- Top bar -->
    <header
      class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-zinc-800/60 bg-zinc-900/70 px-4 backdrop-blur"
    >
      <Link
        :href="route('dashboard', { organization: org })"
        class="font-semibold"
      >
        CRM Beast
      </Link>

      <div class="flex items-center gap-3">
        <UserMenu />
      </div>
    </header>

    <div class="flex">
      <!-- Quick access rail -->
      <aside
        class="flex flex-col gap-2 border-r border-zinc-800/60 bg-zinc-900/60 px-3 py-4"
      >
        <Link
          :href="route('clients.index', { organization: org })"
          class="glass-btn"
          title="Clients"
        >
          ≡
        </Link>

        <Link
          :href="route('projects.index', { organization: org })"
          class="glass-btn"
          title="Projects"
        >
          ▢
        </Link>

        <Link
          :href="route('tasks.index', { organization: org })"
          class="glass-btn"
          title="Tasks"
        >
          ≣
        </Link>

        <Link
          :href="route('billing.index', { organization: org })"
          class="glass-btn"
          title="Billing"
        >
          $
        </Link>
      </aside>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.glass-btn {
  @apply grid h-10 w-10 place-items-center rounded-2xl bg-zinc-900/70 ring-1 ring-zinc-800/50 hover:bg-zinc-800;
}
</style>
