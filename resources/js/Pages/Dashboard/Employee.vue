<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ActivityFeed from '@/Components/ActivityFeed.vue'
import Card from '@/Components/ui/Card.vue'
import ClockWidget from '@/Components/Attendance/ClockWidget.vue'
import TaskDrawer from '@/Components/tasks/TaskDrawer.vue'

defineOptions({ layout: AuthenticatedLayout })

type TaskItem = {
  id: number
  title: string
  status: string | null
  due_date: string | null
  priority: string | null
  project: { id: number; title: string } | null
}

type ProjectItem = {
  id: number
  title: string
  status: string | null
  updated_at: string | null
}

const props = defineProps<{
  org: { id?: number; name?: string; slug?: string }
  my_tasks: TaskItem[]
  my_projects: ProjectItem[]
  recent_activity: Array<{
    id: number
    description: string
    properties: Record<string, unknown> | null
    created_at: string | null
    user: { id: number; name: string } | null
  }>
  tasks_due_today: number
  currentAttendance?: {
    id: number
    clock_in_at: string
    clock_out_at: string | null
    status: string
  } | null
}>()

const page = usePage<any>()
const userName = computed(() => page.props?.auth?.user?.name ?? 'there')

const orgSlug = computed(() => {
  const o = props.org
  if (typeof o === 'string') return o
  return o?.slug ?? 'acme'
})

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
})

const drawerOpen = ref(false)
const drawerTaskId = ref<number | null>(null)

function openTask(taskId: number) {
  drawerTaskId.value = taskId
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
  drawerTaskId.value = null
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusBadgeClass(status: string | null): string {
  if (!status) return 'bg-white/10 text-white/70'
  const s = String(status).toLowerCase()
  if (['done', 'completed', 'approved'].includes(s)) return 'bg-emerald-900/50 text-emerald-300'
  if (['in progress', 'in_progress', 'submitted'].includes(s)) return 'bg-blue-900/50 text-blue-300'
  if (['urgent', 'high'].includes(s)) return 'bg-amber-900/50 text-amber-300'
  return 'bg-white/10 text-white/70'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Greeting + Clock Widget -->
    <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-white">
          {{ greeting }}, {{ userName }}.
        </h1>
        <p class="mt-1 text-white/60">
          You have <strong class="text-white">{{ tasks_due_today }}</strong> {{ tasks_due_today === 1 ? 'task' : 'tasks' }} due today.
        </p>
      </div>
      <ClockWidget
        v-if="currentAttendance !== undefined"
        :current="currentAttendance"
        :organization-slug="orgSlug"
      />
    </section>

    <!-- Section 1: My Priorities -->
    <section>
      <Card title="My Priorities" class="card-neo">
        <div v-if="my_tasks.length > 0" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-white/50 border-b border-white/10">
                <th class="pb-2 pr-3">Task</th>
                <th class="pb-2 pr-3">Project</th>
                <th class="pb-2 pr-3 w-28">Due Date</th>
                <th class="pb-2 w-24">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="task in my_tasks"
                :key="task.id"
                class="cursor-pointer border-b border-white/5 transition hover:bg-white/5"
                @click="openTask(task.id)"
              >
                <td class="py-3 pr-3">
                  <span class="font-medium text-white">{{ task.title }}</span>
                </td>
                <td class="py-3 pr-3">
                  <Link
                    v-if="task.project"
                    :href="route('projects.show', { organization: orgSlug, project: task.project.id })"
                    class="text-white/70 hover:text-white hover:underline"
                    @click.stop
                  >
                    {{ task.project.title }}
                  </Link>
                  <span v-else class="text-white/40">—</span>
                </td>
                <td class="py-3 pr-3 text-white/80">
                  {{ formatDate(task.due_date) }}
                </td>
                <td class="py-3">
                  <span
                    class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="statusBadgeClass(task.status)"
                  >
                    {{ task.status ?? '—' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-8 text-center text-white/50">
          No open tasks. Great job!
        </p>
      </Card>
    </section>

    <!-- Section 2: My Active Projects -->
    <section>
      <Card title="My Active Projects" class="card-neo">
        <div
          v-if="my_projects.length > 0"
          class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
        >
          <Link
            v-for="project in my_projects"
            :key="project.id"
            :href="route('projects.show', { organization: orgSlug, project: project.id })"
            class="group rounded-xl border border-white/10 bg-white/5 p-4 transition hover:border-[var(--primary)] hover:bg-white/10"
          >
            <h3 class="font-medium text-white group-hover:text-[var(--primary)]">
              {{ project.title }}
            </h3>
            <p v-if="project.status" class="mt-1 text-xs text-white/50">
              {{ project.status }}
            </p>
          </Link>
        </div>
        <p v-else class="py-8 text-center text-white/50">
          No active projects assigned.
        </p>
      </Card>
    </section>

    <!-- Section 3: Recent Updates -->
    <section>
      <Card title="Recent Updates" class="card-neo">
        <ActivityFeed :activities="recent_activity ?? []" />
      </Card>
    </section>

    <TaskDrawer
      :show="drawerOpen"
      :task-id="drawerTaskId"
      :organization-slug="orgSlug"
      @close="closeDrawer"
    />
  </div>
</template>
