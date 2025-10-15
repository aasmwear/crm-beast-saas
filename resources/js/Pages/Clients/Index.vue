<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@ziggy'

type Client = {
  id: number
  company_name: string
  niche: string | null
  industry: string | null
  status: string | null
}

const props = defineProps<{
  clients: Client[]
  filters?: { q?: string }
}>()

const org = computed(() => (route as any)().params.organization as string)
const q = ref(props.filters?.q ?? '')

// live search (debounced-ish but simple)
let t: number | undefined
watch(q, (val) => {
  clearTimeout(t)
  t = window.setTimeout(() => {
    router.get(
      route('clients.index', { organization: org.value }),
      { q: val },
      { preserveState: true, replace: true },
    )
  }, 300)
})
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-semibold text-slate-100">Clients</h1>
      <Link
        :href="route('clients.create', { organization: org })"
        class="px-3 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 text-white"
      >
        New
      </Link>
    </div>

    <div class="mb-4">
      <input
        v-model="q"
        type="search"
        placeholder="Search company, industry, niche…"
        class="w-full sm:w-96 rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
      />
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-800">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-900 text-slate-400">
          <tr>
            <th class="px-4 py-3 w-[40%]">Company</th>
            <th class="px-4 py-3">Niche</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
          <tr v-for="c in props.clients" :key="c.id" class="bg-slate-950/40 hover:bg-slate-900/40">
            <td class="px-4 py-3 text-slate-200">
              {{ c.company_name }}
            </td>
            <td class="px-4 py-3 text-slate-300">
              {{ c.niche ?? '—' }}
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-200">
                {{ c.status ?? '—' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="route('clients.show', { organization: org, client: c.id })"
                class="text-indigo-400 hover:text-indigo-300 mr-3"
              >View</Link>
              <Link
                :href="route('clients.edit', { organization: org, client: c.id })"
                class="text-indigo-400 hover:text-indigo-300"
              >Edit</Link>
            </td>
          </tr>
          <tr v-if="!props.clients || props.clients.length === 0">
            <td class="px-4 py-6 text-center text-slate-400" colspan="4">
              No clients found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
