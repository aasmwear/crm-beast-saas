<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ projects: Array<{id:number; name:string; status?:string|null; project_manager_id?:number|null; created_at?:string}> }>();
const rows = computed(() => props.projects);
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">Projects List</h1>
    <div class="glass rounded-2xl overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-white/5">
          <tr>
            <th class="text-left p-3">Name</th>
            <th class="text-left p-3">Status</th>
            <th class="text-left p-3">PM</th>
            <th class="text-left p-3">Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in rows" :key="p.id" class="border-t border-white/5">
            <td class="p-3 font-medium">{{ p.name }}</td>
            <td class="p-3">{{ p.status || '—' }}</td>
            <td class="p-3">#{{ p.project_manager_id ?? '—' }}</td>
            <td class="p-3 text-slate-400">{{ p.created_at ? new Date(p.created_at).toLocaleDateString() : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
