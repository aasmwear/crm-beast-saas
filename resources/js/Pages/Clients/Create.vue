<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import route from '@ziggy'

const form = useForm({
  company_name: '',
  niche: '',
  status: 'active',
})

function submit() {
  form.post(
    route('clients.store', { organization: route().params.organization }),
    { preserveScroll: true }
  )
}
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-semibold mb-6">Create Client</h1>

    <form @submit.prevent="submit" class="space-y-6">
      <div>
        <label class="block text-sm font-medium mb-1">Company name</label>
        <input
          v-model="form.company_name"
          class="w-full rounded border px-3 py-2"
          type="text"
          autocomplete="off"
        />
        <div v-if="form.errors.company_name" class="text-red-500 text-sm mt-1">
          {{ form.errors.company_name }}
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Niche</label>
        <input
          v-model="form.niche"
          class="w-full rounded border px-3 py-2"
          type="text"
          autocomplete="off"
        />
        <div v-if="form.errors.niche" class="text-red-500 text-sm mt-1">
          {{ form.errors.niche }}
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select v-model="form.status" class="w-full rounded border px-3 py-2">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
          {{ form.errors.status }}
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="submit"
          class="px-4 py-2 rounded bg-indigo-600 text-white disabled:opacity-50"
          :disabled="form.processing"
        >
          Save
        </button>

        <Link
          :href="route('clients.index', { organization: route().params.organization })"
          class="text-gray-500 hover:text-gray-700"
        >
          Cancel
        </Link>
      </div>
    </form>
  </div>
</template>
