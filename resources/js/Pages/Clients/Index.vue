<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import { route } from 'ziggy-js'

const page = usePage()
const q = ref<string>((page.props as any).filters?.q || '')

function search() {
  window.location.href = route('clients.index', { organization: (route() as any).params.organization, q: q.value })
}
function exportCsv() {
  window.location.href = route('clients.index', { organization: (route() as any).params.organization, export: 'csv', q: q.value })
}
</script>

<template>
  <div class="px-6 py-4 text-white">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Clients</h1>
      <div class="flex gap-2">
        <input v-model="q" @keyup.enter="search" placeholder="Search…" class="rounded-lg bg-black/40 px-3 py-2" />
        <button @click="search" class="rounded-xl bg-indigo-600 px-3 py-2">Search</button>
        <button @click="exportCsv" class="rounded-xl bg-slate-700 px-3 py-2">Export CSV</button>
        <Link :href="route('clients.create', (route() as any).params.organization)" class="rounded-xl bg-green-600 px-3 py-2">New</Link>
      </div>
    </div>

    <div class="mt-4 rounded-2xl bg-black/30">
      <table class="w-full text-left">
        <thead class="text-slate-300">
          <tr>
            <th class="px-4 py-3">Company</th>
            <th class="px-4 py-3">Contact</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in ( ($page.props as any).clients.data )" :key="c.id" class="border-t border-white/5">
            <td class="px-4 py-3">{{ c.company_name }}</td>
            <td class="px-4 py-3">
              <div>{{ c.primary_contact_name }}</div>
              <div class="text-slate-400 text-sm">{{ c.primary_contact_email }}</div>
            </td>
            <td class="px-4 py-3">{{ c.status || '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link
  :href="route('clients.edit', { organization: route().params.organization, client: row.id })">
  Edit
</Link>


            </td>
          </tr>
        </tbody>
      </table>

      <div class="p-4 text-slate-400" v-if="!($page.props as any).clients.data.length">No clients yet.</div>
    </div>
  </div>
</template>
