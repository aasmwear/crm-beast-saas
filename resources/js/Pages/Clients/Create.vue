<script setup lang="ts">
import { useForm, usePage, Link } from '@inertiajs/vue3'
import route from 'ziggy-js'
import { computed } from 'vue'

const page = usePage()
const org = computed(
  () => (page.props.tenant as any)?.slug || (route() as any).params.organization
)

const form = useForm({
  company_name: '',
  email: '',
  phone_1: '',
  status: '',
  niche: '',
})

// 👉 keep TS simple for templates
const errors = computed<Record<string, string>>(
  () => ((form as any).errors || {}) as Record<string, string>
)

function submit() {
  form.post(route('clients.store', { organization: org.value }))
}
</script>

<template>
  <div class="p-6 max-w-2xl space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">New Client</h1>
      <Link :href="route('clients.index', { organization: org })" class="text-neutral-300 hover:underline">Back</Link>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm mb-1">Company Name</label>
        <input v-model="form.company_name" class="w-full rounded-md bg-neutral-900 border border-neutral-700 px-3 py-2" />
        <div v-if="errors.company_name" class="text-red-400 text-sm mt-1">{{ errors.company_name }}</div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Email</label>
          <input v-model="form.email" class="w-full rounded-md bg-neutral-900 border border-neutral-700 px-3 py-2" />
          <div v-if="errors.email" class="text-red-400 text-sm mt-1">{{ errors.email }}</div>
        </div>
        <div>
          <label class="block text-sm mb-1">Phone</label>
          <input v-model="form.phone_1" class="w-full rounded-md bg-neutral-900 border border-neutral-700 px-3 py-2" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Status</label>
          <input v-model="form.status" class="w-full rounded-md bg-neutral-900 border border-neutral-700 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm mb-1">Niche</label>
          <input v-model="form.niche" class="w-full rounded-md bg-neutral-900 border border-neutral-700 px-3 py-2" />
        </div>
      </div>

      <div class="pt-2">
        <button :disabled="form.processing" class="rounded-md bg-emerald-600 px-4 py-2">Save</button>
      </div>
    </form>
  </div>
</template>
