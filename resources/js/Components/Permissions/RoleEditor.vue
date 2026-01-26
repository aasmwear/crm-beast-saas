<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
const props = defineProps<{ roles: any[], permissions: any[] }>()
const page = usePage()
const org = (page.props as any).tenant?.slug ?? (page.props as any).organization?.slug ?? 'acme'
const entities = ['users','clients','projects','tasks','announcements','attendance','departments']
const actions = ['create','read','update','delete']
const form = useForm({ role_id: null as any, permissions_map: {} as any })
function toggle(e: string, a: string){ if(!form.permissions_map[e]) form.permissions_map[e] = { actions: {}, fields: {} }; form.permissions_map[e].actions[a] = !form.permissions_map[e].actions[a] }
function save(){ form.post(route('roles.save', { organization: org })) }
</script>
<template>
  <div class="p-6 space-y-6">
    <div class="bg-gray-900 rounded-xl p-4 text-white">
      <div class="flex gap-3 items-center mb-4"><select v-model="form.role_id" class="px-3 py-2 rounded bg-gray-800"><option :value="null" disabled>Select Role</option><option v-for="r in props.roles" :key="r.id" :value="r.id">{{ r.name }}</option></select><button @click="save" class="px-3 py-2 rounded bg-indigo-600">Save</button></div>
      <table class="w-full text-left text-sm"><thead class="bg-gray-800"><tr><th class="p-2">Entity</th><th v-for="a in actions" :key="a" class="p-2 capitalize">{{ a }}</th></tr></thead>
        <tbody><tr v-for="e in entities" :key="e" class="border-t border-gray-800"><td class="p-2 capitalize">{{ e }}</td><td v-for="a in actions" :key="a" class="p-2"><input type="checkbox" :checked="form.permissions_map[e]?.actions?.[a] || false" @change="toggle(e,a)" /></td></tr></tbody></table>
    </div>
  </div>
</template>
