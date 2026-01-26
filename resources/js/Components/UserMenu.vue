<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'

const { props } = usePage<{ auth: { user: { name: string, email: string } } }>()
const form = useForm({})
const logout = () => form.post(route('logout'))
</script>

<template>
  <div class="relative">
    <details class="group">
      <summary class="cursor-pointer rounded-xl px-3 py-2 bg-zinc-800/70 hover:bg-zinc-700 text-sm">
        {{ props.auth?.user?.name ?? 'Account' }}
      </summary>
      <div class="absolute right-0 mt-2 w-56 rounded-xl bg-zinc-900/95 ring-1 ring-zinc-700/50 shadow-xl p-2">
        <div class="px-3 py-2 text-xs text-zinc-400">{{ props.auth?.user?.email }}</div>
        <Link :href="route('profile.edit')" class="block px-3 py-2 rounded-lg hover:bg-zinc-800">Profile</Link>
        <button @click="logout" class="block w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-800">Log out</button>
      </div>
    </details>
  </div>
</template>
