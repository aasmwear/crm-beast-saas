<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineOptions({ layout: PortalLayout })

const props = defineProps<{
  clientName: string
  projects: Array<{
    id: number
    title: string
    status: string | null
    start_date: string | null
    end_date: string | null
  }>
  invoices: Array<{
    id: number
    number: string
    issue_date: string
    due_date: string
    status: string
    total_cents: number
    currency: string
    paid_at: string | null
  }>
}>()

const r = (name: string, params: Record<string, number> = {}) =>
  (window as any).route ? (window as any).route(name, params) : '#'

function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(cents / 100)
}

function isPaid(status: string) {
  return status?.toLowerCase() === 'paid'
}

function payInvoice(inv: { id: number }) {
  router.post(r('portal.invoices.pay', { invoice: inv.id }))
}
</script>

<template>
  <div class="space-y-8">
    <section>
      <h1 class="text-2xl font-semibold text-white">
        Welcome, {{ clientName }}
      </h1>
      <p class="mt-1 text-sm text-white/60">
        View your projects below.
      </p>
    </section>

    <section>
      <h2 class="text-lg font-medium text-white mb-4">My Projects</h2>
      <div v-if="projects.length === 0" class="rounded-2xl border border-white/10 bg-slate-900/40 p-8 text-center text-white/60">
        No projects yet.
      </div>
      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="project in projects"
          :key="project.id"
          :href="r('portal.projects.show', { project: project.id })"
          class="block rounded-xl border border-white/10 bg-slate-900/40 p-5 transition hover:border-white/20 hover:bg-slate-800/40"
        >
          <div class="flex items-start justify-between gap-3">
            <h3 class="font-medium text-white truncate">
              {{ project.title }}
            </h3>
            <span
              v-if="project.status"
              class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70"
            >
              {{ project.status }}
            </span>
          </div>
          <p class="mt-2 text-xs text-white/50">
            {{ project.start_date ? new Date(project.start_date).toLocaleDateString() : '—' }}
            <template v-if="project.end_date">
              → {{ new Date(project.end_date).toLocaleDateString() }}
            </template>
          </p>
          <p class="mt-2 text-sm text-indigo-300">View project →</p>
        </Link>
      </div>
    </section>

    <section>
      <h2 class="text-lg font-medium text-white mb-4">My Invoices</h2>
      <div v-if="!invoices || invoices.length === 0" class="rounded-2xl border border-white/10 bg-slate-900/40 p-8 text-center text-white/60">
        No invoices yet.
      </div>
      <div v-else class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-white/50 border-b border-white/10">
              <th class="p-4">Number</th>
              <th class="p-4">Issue / Due</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Total</th>
              <th class="p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in invoices" :key="inv.id" class="border-b border-white/5">
              <td class="p-4 font-mono text-white/90">{{ inv.number }}</td>
              <td class="p-4 text-white/70">
                {{ new Date(inv.issue_date).toLocaleDateString() }} / {{ new Date(inv.due_date).toLocaleDateString() }}
              </td>
              <td class="p-4">
                <span
                  v-if="isPaid(inv.status)"
                  class="rounded-full bg-emerald-500/30 px-2 py-0.5 text-xs font-medium text-emerald-200"
                  :title="inv.paid_at ? `Paid on ${new Date(inv.paid_at).toLocaleDateString()}` : ''"
                >
                  PAID{{ inv.paid_at ? ` · ${new Date(inv.paid_at).toLocaleDateString()}` : '' }}
                </span>
                <span v-else class="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70">
                  {{ inv.status }}
                </span>
              </td>
              <td class="p-4 text-right text-white/90">{{ formatMoney(inv.total_cents, inv.currency) }}</td>
              <td class="p-4 flex items-center gap-3">
                <a
                  :href="r('portal.invoices.download', { invoice: inv.id })"
                  target="_blank"
                  rel="noopener"
                  class="text-indigo-300 hover:text-indigo-200 text-sm"
                >
                  PDF
                </a>
                <button
                  v-if="!isPaid(inv.status)"
                  type="button"
                  class="rounded-full bg-indigo-500 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-400"
                  @click="payInvoice(inv)"
                >
                  Pay Now
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
