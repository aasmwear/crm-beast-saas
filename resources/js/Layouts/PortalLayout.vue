<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-white">
    <header class="border-b border-white/10 bg-slate-900/60">
      <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-8">
          <Link href="/portal/dashboard" class="text-lg font-semibold text-white">
            Portal
          </Link>
          <nav class="flex items-center gap-4 text-sm">
            <Link
              href="/portal/dashboard"
              class="text-white/70 hover:text-white transition"
            >
              My Projects
            </Link>
          </nav>
        </div>
        <div class="flex items-center gap-4">
          <Link
            href="/profile"
            class="text-sm text-white/70 hover:text-white transition"
          >
            Profile
          </Link>
          <Link
            href="/logout"
            method="post"
            as="button"
            class="rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-sm text-white/80 hover:bg-white/10 transition"
          >
            Logout
          </Link>
        </div>
      </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
      <slot />
    </main>
  </div>
</template>
