<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3'
import { inject } from 'vue'
const route = inject('route') as typeof import('ziggy-js')['default']
import { computed } from 'vue'

const props = defineProps<{ client: any }>()
const page = usePage()
const org = computed(() => (page.props.tenant as any)?.slug || (route() as any).params.organization)

function destroyClient() {
  if (!confirm('Delete this client?')) return
  router.delete(route('clients.destroy', { organization: org.value, client: props.client.id }))
}
</script>

<template>
  <div class="p-6 max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">{{ props.client.company_name }}</h1>
      <div class="flex gap-3">
        <Link :href="route('clients.edit', { organization: org, client: props.client.id })" class="rounded-md bg-indigo-600 px-3 py-2 text-sm">Edit</Link>
        <button @click="destroyClient" class="rounded-md bg-red-600 px-3 py-2 text-sm">Delete</button>
        <Link :href="route('clients.index', { organization: org })" class="text-neutral-300 hover:underline">Back</Link>
      </div>
    </div>

    <div class="rounded-xl bg-neutral-900 border border-neutral-800 p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div><div class="text-neutral-400 text-sm">Email</div><div>{{ props.client.email || '—' }}</div></div>
      <div><div class="text-neutral-400 text-sm">Phone</div><div>{{ props.client.phone_1 || '—' }}</div></div>
      <div><div class="text-neutral-400 text-sm">Status</div><div>{{ props.client.status || '—' }}</div></div>
      <div><div class="text-neutral-400 text-sm">Niche</div><div>{{ props.client.niche || '—' }}</div></div>
    </div>
  </div>
</template>
