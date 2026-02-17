<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const r = (name: string, params: Record<string, string | number> = {}) =>
  (window as any).route ? (window as any).route(name, params) : '#'

const props = defineProps<{
  organizationSlug: string
  clients: Array<{ id: number; company_name: string; currency: string }>
  projects: Array<{ id: number; client_id: number; title: string }>
}>()

const form = useForm({
  client_id: '' as number | '',
  project_id: '' as number | '',
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
})

const projectsForClient = computed(() => {
  if (!form.client_id) return []
  return props.projects.filter((p) => p.client_id === Number(form.client_id))
})

const submit = () => {
  form.post(r('invoices.store', { organization: props.organizationSlug }), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <a :href="r('invoices.index', { organization: organizationSlug })" class="text-white/60 hover:text-white text-sm">← Invoices</a>
      <h1 class="text-2xl font-semibold text-white">New Invoice</h1>
    </div>

    <form @submit.prevent="submit" class="max-w-xl space-y-6 rounded-2xl border border-white/10 bg-slate-950/60 p-6">
      <div>
        <label class="block text-sm font-medium text-white/70 mb-1">Client *</label>
        <select
          v-model="form.client_id"
          required
          class="w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2 text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">Select client</option>
          <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
        </select>
        <p v-if="form.errors.client_id" class="mt-1 text-sm text-red-400">{{ form.errors.client_id }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-white/70 mb-1">Project (optional)</label>
        <select
          v-model="form.project_id"
          class="w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2 text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">No project</option>
          <option v-for="p in projectsForClient" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-white/70 mb-1">Issue date *</label>
          <input
            v-model="form.issue_date"
            type="date"
            required
            class="w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
          <p v-if="form.errors.issue_date" class="mt-1 text-sm text-red-400">{{ form.errors.issue_date }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-white/70 mb-1">Due date *</label>
          <input
            v-model="form.due_date"
            type="date"
            required
            class="w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
          <p v-if="form.errors.due_date" class="mt-1 text-sm text-red-400">{{ form.errors.due_date }}</p>
        </div>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Creating…' : 'Create Invoice' }}
        </button>
        <a :href="r('invoices.index', { organization: organizationSlug })" class="rounded-xl border border-white/20 px-4 py-2 text-sm text-white/80 hover:bg-white/10">
          Cancel
        </a>
      </div>
    </form>
  </div>
</template>
