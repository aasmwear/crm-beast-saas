<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@ziggy'
import { computed } from 'vue'

type Client = {
  id: number
  company_name: string
  niche: string | null
  industry: string | null
  website: string | null
  primary_contact_name: string | null
  primary_contact_email: string | null
  primary_contact_phone: string | null
  address: string | null
  status: string | null
}

const props = defineProps<{ client: Client }>()
const org = computed(() => (route as any)().params.organization as string)

const form = useForm({
  company_name: props.client.company_name ?? '',
  niche: props.client.niche ?? '',
  industry: props.client.industry ?? '',
  website: props.client.website ?? '',
  primary_contact_name: props.client.primary_contact_name ?? '',
  primary_contact_email: props.client.primary_contact_email ?? '',
  primary_contact_phone: props.client.primary_contact_phone ?? '',
  address: props.client.address ?? '',
  status: props.client.status ?? 'active',
})

function submit() {
  form.put(route('clients.update', { organization: org.value, client: props.client.id }))
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold text-slate-100 mb-6">Edit Client</h1>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <label class="block text-sm text-slate-300 mb-1">Company name *</label>
        <input v-model="form.company_name" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
        <div v-if="form.errors.company_name" class="text-red-400 text-sm mt-1">{{ form.errors.company_name }}</div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Niche</label>
          <input v-model="form.niche" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
          <div v-if="form.errors.niche" class="text-red-400 text-sm mt-1">{{ form.errors.niche }}</div>
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Industry</label>
          <input v-model="form.industry" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
          <div v-if="form.errors.industry" class="text-red-400 text-sm mt-1">{{ form.errors.industry }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Website</label>
          <input v-model="form.website" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
          <div v-if="form.errors.website" class="text-red-400 text-sm mt-1">{{ form.errors.website }}</div>
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Status</label>
          <select v-model="form.status" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </select>
          <div v-if="form.errors.status" class="text-red-400 text-sm mt-1">{{ form.errors.status }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Contact name</label>
          <input v-model="form.primary_contact_name" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Contact email</label>
          <input v-model="form.primary_contact_email" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Contact phone</label>
          <input v-model="form.primary_contact_phone" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100" />
        </div>
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-1">Address</label>
        <textarea v-model="form.address" rows="3" class="w-full rounded-md bg-slate-800 border border-slate-700 px-3 py-2 text-slate-100"></textarea>
      </div>

      <div class="flex items-center gap-3">
        <button :disabled="form.processing" class="px-4 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50">Update</button>
        <Link :href="route('clients.index', { organization: org })" class="text-slate-400 hover:text-slate-300">Cancel</Link>
      </div>
    </form>
  </div>
</template>
