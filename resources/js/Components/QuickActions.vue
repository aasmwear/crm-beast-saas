<script setup lang="ts">
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
const open = ref(false)
const org = (usePage().props as any).tenant?.slug ?? 'acme'
defineExpose({ open })
</script>

<template>
  <dialog ref="open" class="rounded-2xl bg-zinc-900/95 p-4 w-[480px]">
    <h3 class="font-semibold mb-3">Quick actions</h3>
    <div class="space-y-2">
      <Link :href="route('clients.create', { organization: org })" class="cta">New Client</Link>
      <Link :href="route('projects.create', { organization: org })" class="cta">New Project</Link>
      <Link :href="route('tasks.create', { organization: org })" class="cta">New Task</Link>
    </div>
  </dialog>
</template>

<style scoped>
.cta{ @apply block px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700; }
</style>
