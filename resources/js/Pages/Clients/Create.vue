<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'


const org = (usePage().props.tenant as any)?.slug || route().params.organization

const form = useForm({
  company_name: '', primary_contact_name: '', primary_contact_email: '',
  primary_contact_phone: '', industry: '', niche: '', website: '', address: '',
  status: ''
})

function submit() {
  form.post(route('clients.store', { organization: org }))

}
</script>

<template>
  <div class="p-6 text-white">
    <h1 class="text-2xl font-semibold mb-4">Create Client</h1>
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
      <button @click="submit" class="rounded-xl bg-green-600 px-4 py-2">Save</button>
    </div>
  </div>
</template>
