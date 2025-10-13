<script setup lang="ts">
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import route from 'ziggy-js'
import { computed, ref } from 'vue'

type Paged<T> = {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
  tenant: { id: number; slug: string; name: string }
  items: Paged<any>
  filters: { q?: string }
}>()

const org = computed(() => props.tenant?.slug || (route() as any).params.organization)
const q = ref(props.filters?.q ?? '')

function doSearch() {
  router.get(route('clients.index', { organization: org.value }), { q: q.value }, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Clients</h1>
      <div class="flex gap-2">
        <input
          v-model="q"
          @keyup.enter="doSearch"
          placeholder="Search…"
          class="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 text-sm focus:outline-none"
        />
        <button @click="doSearch" class="rounded-md bg-indigo-600 px-3 py-2 text-sm">Search</button>
        <Link :href="route('clients.create', { organization: org })" class="rounded-md bg-emerald-600 px-3 py-2 text-sm">New</Link>
      </div>
    </div>

    <div class="rounded-xl bg-neutral-900 border border-neutral-800">
      <table class="w-full text-sm">
        <thead class="text-neutral-400">
          <tr>
            <th class="text-left p-3">Company</th>
            <th class="text-left p-3">Email</th>
            <th class="text-left p-3">Status</th>
            <th class="text-right p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in props.items.data" :key="row.id" class="border-t border-neutral-800">
            <td class="p-3">{{ row.company_name }}</td>
            <td class="p-3">{{ row.email }}</td>
            <td class="p-3">{{ row.status || '—' }}</td>
            <td class="p-3 text-right">
              <Link :href="route('clients.show', { organization: org, client: row.id })" class="text-sky-400 hover:underline mr-3">View</Link>
              <Link :href="route('clients.edit', { organization: org, client: row.id })" class="text-indigo-400 hover:underline">Edit</Link>
            </td>
          </tr>
          <tr v-if="!props.items.data.length">
            <td colspan="4" class="p-6 text-center text-neutral-400">No clients yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav class="flex gap-1">
      <Link
        v-for="l in props.items.links"
        :key="l.label + l.url"
        :href="l.url || '#'"
        :class="['px-3 py-1 rounded-md text-sm', l.active ? 'bg-neutral-700' : 'bg-neutral-800 hover:bg-neutral-700']"
        v-html="l.label"
        preserve-state
      />
    </nav>
  </div>
</template>
