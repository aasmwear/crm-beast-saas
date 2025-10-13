<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps<{ client: any }>()
const org = (usePage().props.tenant as any)?.slug || route().params.organization

function submit() {
  form.put(route('clients.update', {
    organization: route().params.organization,
    client: props.client.id
  }))
}

</script>

<template>
  <div class="p-6 text-white">
    <h1 class="text-2xl font-semibold mb-4">Edit Client</h1>
    <div class="grid gap-4 md:grid-cols-2">
      <input v-model="form.company_name" placeholder="Company name" class="rounded bg-black/40 px-3 py-2" />
      <input v-model="form.primary_contact_email" placeholder="Primary email" class="rounded bg-black/40 px-3 py-2" />
      <input v-model="form.primary_contact_name" placeholder="Primary contact name" class="rounded bg-black/40 px-3 py-2" />
      <input v-model="form.primary_contact_phone" placeholder="Phone" class="rounded bg-black/40 px-3 py-2" />
      <input v-model="form.industry" placeholder="Industry" class="rounded bg-black/40 px-3 py-2" />
      <input v-model="form.niche" placeholder="Niche" class="rounded bg-black/40 px-3 py-2" />
      <input v-model="form.website" placeholder="Website (https://…)" class="rounded bg-black/40 px-3 py-2 md:col-span-2" />
      <input v-model="form.address" placeholder="Address" class="rounded bg-black/40 px-3 py-2 md:col-span-2" />
    </div>
    <div class="mt-6">
      <button @click="submit" class="rounded-xl bg-indigo-600 px-4 py-2">Update</button>
    </div>
  </div>
</template>
