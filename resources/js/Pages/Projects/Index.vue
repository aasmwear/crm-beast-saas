<script setup lang="ts">
import { useForm, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Sets the layout for this page
defineOptions({ layout: AuthenticatedLayout })

const page = usePage()
const org = (page.props as any).tenant?.slug ?? (page.props as any).organization?.slug ?? 'acme'
const props = defineProps<{ projects: any, cstManagers: any[] }>()

// Quick Create Form Logic (kept from previous version)
const form = useForm({
  client_id: null as any,
  title: '',
  project_code: '',
  description: '',
  project_manager_id: null as any,
  department_id: null as any,
  status: 'Active',
  budget: '',
  price: '',
  billable: true,
})
function submit() {
  form.post(route('projects.store', { organization: org }))
}
</script>

<template>
  <div class="space-y-6">
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-[rgba(139,92,246,0.25)] via-[rgba(18,18,40,0.6)] to-transparent border border-white/10 shadow-[0_10px_40px_rgba(139,92,246,.25)] p-6 backdrop-blur-xl">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight text-white">Projects</h1>
          <p class="text-sm text-white/60 mt-1">Manage and track all organizational projects.</p>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="route('projects.index', { organization: org })" class="px-3 py-2 rounded-lg bg-[var(--primary)] text-white/90 hover:opacity-90">List</Link>
          <Link :href="route('projects.board', { organization: org })" class="px-3 py-2 rounded-lg bg-white/10 text-white/90 hover:bg-white/20">Board</Link>
          <Link :href="route('projects.calendar', { organization: org })" class="px-3 py-2 rounded-lg bg-white/10 text-white/90 hover:bg-white/20">Calendar</Link>
        </div>
      </div>
    </div>
    
    <div class="bg-gray-900 rounded-xl p-4 text-white">
      <div class="font-semibold mb-3">Quick Create Project</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input v-model="form.title" placeholder="Title" class="px-3 py-2 rounded bg-gray-800" />
        <input v-model="form.project_code" placeholder="Code" class="px-3 py-2 rounded bg-gray-800" />
        <select v-model="form.project_manager_id" class="px-3 py-2 rounded bg-gray-800">
          <option :value="null" disabled>Select PM (CST only)</option>
          <option v-for="u in props.cstManagers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <input v-model="form.price" placeholder="Price" type="number" class="px-3 py-2 rounded bg-gray-800" />
        <label class="flex gap-2 items-center"><input type="checkbox" v-model="form.billable" /> Billable</label>
        <button @click="submit" class="px-3 py-2 rounded bg-indigo-600">Create</button>
      </div>
    </div>

    <div class="bg-gray-900 rounded-xl overflow-hidden text-white">
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-800 text-gray-300">
          <tr>
            <th class="p-3">Title</th>
            <th class="p-3">Client</th>
            <th class="p-3">PM</th>
            <th class="p-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in props.projects.data" :key="p.id" class="border-t border-gray-800">
            <td class="p-3">{{ p.title }}</td>
            <td class="p-3">{{ p.client?.company_name ?? '—' }}</td>
            <td class="p-3">{{ p.manager?.name ?? '—' }}</td>
            <td class="p-3">{{ p.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex justify-center mt-4">
      <template v-for="(link, key) in props.projects.links" :key="key">
        <Link
          v-if="link.url"
          :href="link.url"
          v-html="link.label"
          class="px-3 py-2 text-white text-sm rounded mx-1"
          :class="{'bg-indigo-600': link.active, 'bg-gray-800 hover:bg-gray-700': !link.active}"
        />
        <span
          v-else
          v-html="link.label"
          class="px-3 py-2 text-gray-500 text-sm rounded mx-1 bg-gray-800"
        />
      </template>
    </div>
  </div>
</template>