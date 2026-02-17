<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const r = (name: string, params: Record<string, string | number> = {}) =>
  (window as any).route ? (window as any).route(name, params) : '#'

const props = defineProps<{
  organizationSlug: string
  invoices: {
    data: Array<{
      id: number
      number: string
      issue_date: string
      due_date: string
      status: string
      total_cents: number
      currency: string
      client?: { id: number; company_name: string }
      project?: { id: number; title: string } | null
    }>
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
}>()

function formatMoney(cents: number, currency: string) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(cents / 100)
}

function statusClass(s: string) {
  const map: Record<string, string> = {
    Draft: 'bg-amber-900/50 text-amber-300',
    Sent: 'bg-blue-900/50 text-blue-300',
    Paid: 'bg-emerald-900/50 text-emerald-300',
    Overdue: 'bg-rose-900/50 text-rose-300',
  }
  return 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ' + (map[s] || 'bg-white/10 text-white/70')
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Invoices</h1>
      <Link
        :href="r('invoices.create', { organization: organizationSlug })"
        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
      >
        New Invoice
      </Link>
    </div>

    <div class="rounded-2xl border border-white/10 bg-slate-950/60 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-white/50 border-b border-white/10">
            <th class="p-4">Number</th>
            <th class="p-4">Client</th>
            <th class="p-4">Project</th>
            <th class="p-4">Issue / Due</th>
            <th class="p-4">Status</th>
            <th class="p-4 text-right">Total</th>
            <th class="p-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in invoices.data" :key="inv.id" class="border-b border-white/5 hover:bg-white/5">
            <td class="p-4 font-mono text-white/90">{{ inv.number }}</td>
            <td class="p-4 text-white/80">{{ inv.client?.company_name ?? '—' }}</td>
            <td class="p-4 text-white/70">{{ inv.project?.title ?? '—' }}</td>
            <td class="p-4 text-white/70">
              {{ new Date(inv.issue_date).toLocaleDateString() }} / {{ new Date(inv.due_date).toLocaleDateString() }}
            </td>
            <td class="p-4">
              <span :class="statusClass(inv.status)">{{ inv.status }}</span>
            </td>
            <td class="p-4 text-right text-white/90">{{ formatMoney(inv.total_cents, inv.currency) }}</td>
            <td class="p-4">
              <Link
                :href="r('invoices.show', { organization: organizationSlug, invoice: inv.id })"
                class="text-indigo-300 hover:text-indigo-200"
              >
                View
              </Link>
            </td>
          </tr>
          <tr v-if="invoices.data.length === 0">
            <td colspan="7" class="p-8 text-center text-white/50">No invoices yet.</td>
          </tr>
        </tbody>
      </table>
      <nav v-if="invoices.links && invoices.links.length > 3" class="flex justify-center gap-2 p-4 border-t border-white/10">
        <Link
          v-for="link in invoices.links"
          :key="link.label"
          :href="link.url ?? '#'"
          :class="[link.active ? 'bg-indigo-600 text-white' : 'bg-white/10 text-white/80', 'rounded px-3 py-1 text-sm']"
          v-html="link.label"
        />
      </nav>
    </div>
  </div>
</template>
