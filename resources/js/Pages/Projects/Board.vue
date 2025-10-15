<script setup lang="ts">
import { route } from '@ziggy'

import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

type Tenant = { id:number; name:string; slug:string }
type Task = {
  id:number; title:string; status:'Backlog'|'In Progress'|'Review'|'Done'
  project_id:number; priority:number|null; due_date:string|null; assignees?: number[]
}
type Project = { id:number; title:string; client_id:number; project_manager_id:number; status:string }

const page = usePage<{ tenant: Tenant; columns: string[]; projects: Project[]; tasks: Task[] }>()
const tenant = computed(() => page.props.tenant)
const cols = computed(() => page.props.columns)
const allTasks = ref<Task[]>([...page.props.tasks])

function tasksIn(col: string) {
  return allTasks.value.filter(t => t.status === col)
}

async function move(task: Task, to: string) {
  if (task.status === to) return
  const old = task.status
  task.status = to as Task['status']
  try {
    await fetch(route('tasks.move', { organization: tenant.value.slug, task: task.id }), {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ status: to }),
      credentials: 'same-origin',
    })
  } catch {
    task.status = old
  }
}
</script>

<template>
  <div class="px-6 md:px-8 lg:px-10 py-6">
    <div class="text-slate-100 text-xl font-semibold mb-4">Pipeline</div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="col in cols" :key="col" class="glass p-3">
        <div class="flex items-center justify-between mb-2">
          <div class="font-medium text-slate-100">{{ col }}</div>
          <div class="text-slate-400 text-xs">{{ tasksIn(col).length }}</div>
        </div>

        <div class="space-y-2">
          <div v-for="t in tasksIn(col)" :key="t.id" class="card">
            <div class="text-slate-100 text-sm font-medium">{{ t.title }}</div>
            <div class="text-slate-400 text-xs">
              #{{ t.id }} · P{{ t.priority ?? 0 }} · {{ t.due_date ? new Date(t.due_date).toLocaleDateString() : '—' }}
            </div>

            <div class="mt-2 flex gap-2">
              <select class="bg-white/5 border border-white/10 rounded-md text-xs text-slate-200"
                      :value="t.status" @change="move(t, ($event.target as HTMLSelectElement).value)">
                <option v-for="opt in cols" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
          </div>

          <div v-if="tasksIn(col).length === 0" class="empty">No tasks</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.glass { @apply rounded-2xl border border-white/10 bg-gradient-to-br from-white/5 to-white/0 shadow-lg shadow-black/20; backdrop-filter: blur(8px); }
.card  { @apply rounded-xl border border-white/10 bg-white/5 p-3; }
.empty { @apply text-slate-400 text-xs italic; }
</style>
