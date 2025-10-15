<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { route } from '@ziggy'

type ClientPayload = {
  id: number
  company_name: string
  industry: string | null
  niche: string | null
  primary_contact_name: string | null
  primary_contact_email: string | null
  primary_contact_phone: string | null
  website: string | null
  address: string | null
  status: string | null
}

const props = defineProps<{ client: ClientPayload }>()

const org = () => (route as any)().params.organization as string

function destroyClient() {
  if (!confirm('Delete this client?')) return
  router.delete(route('clients.destroy', { organization: org(), client: props.client.id }))
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-6">
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-100">{{ props.client.company_name }}</h1>
        <p class="text-slate-400 text-sm">
          <span v-if="props.client.niche">{{ props.client.niche }} • </span>
          <span class="uppercase tracking-wide text-xs text-slate-400">Status:</span>
          <span class="text-slate-200">{{ props.client.status }}</span>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <Link
          :href="route('clients.edit', { organization: org(), client: props.client.id })"
          class="px-3 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 text-white"
        >Edit</Link>

        <button
          type="button"
          @click="destroyClient"
          class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-500 text-white"
        >
          Delete
        </button>
      </div>
    </div>

    <div class="space-y-4 text-slate-200">
      <div><span class="text-slate-400">Industry:</span> {{ props.client.industry ?? '—' }}</div>
      <div><span class="text-slate-400">Website:</span> {{ props.client.website ?? '—' }}</div>
      <div>
        <span class="text-slate-400">Contact:</span>
        {{ props.client.primary_contact_name ?? '—' }} /
        {{ props.client.primary_contact_email ?? '—' }} /
        {{ props.client.primary_contact_phone ?? '—' }}
      </div>
      <div><span class="text-slate-400">Address:</span> <pre class="whitespace-pre-wrap">{{ props.client.address ?? '—' }}</pre></div>
    </div>

    <div class="mt-8">
      <Link :href="route('clients.index', { organization: org() })" class="text-indigo-400 hover:text-indigo-300">
        ← Back to Clients
      </Link>
    </div>
  </div>
</template>
