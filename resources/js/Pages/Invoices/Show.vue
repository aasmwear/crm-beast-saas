<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const r = (name: string, params: Record<string, string | number> = {}) =>
  (window as any).route ? (window as any).route(name, params) : '#'

type Item = {
  id?: number | null
  description: string
  quantity: number
  unit_price_cents: number
  amount_cents?: number
}

const props = defineProps<{
  organizationSlug: string
  invoice: {
    id: number
    number: string
    issue_date: string
    due_date: string
    status: string
    total_cents: number
    currency: string
    notes: string | null
    client: { id: number; company_name: string; address: string | null; currency: string }
    project: { id: number; title: string } | null
    items: Array<Item>
  }
}>()

const inputClass =
  'w-full rounded-lg border border-white/10 bg-slate-900/60 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500'

const toDateOnly = (v: string) => (typeof v === 'string' && v.length >= 10 ? v.slice(0, 10) : v)

type FormItem = { id: number | null; description: string; quantity: number; unit_price_cents: number; amount_cents?: number }
const form = useForm<{ issue_date: string; due_date: string; notes: string; items: FormItem[] }>({
  issue_date: toDateOnly(props.invoice.issue_date),
  due_date: toDateOnly(props.invoice.due_date),
  notes: props.invoice.notes ?? '',
  items: props.invoice.items.length
    ? props.invoice.items.map((i) => ({
        id: (i.id ?? null) as number | null,
        description: i.description,
        quantity: i.quantity,
        unit_price_cents: i.unit_price_cents,
      }))
    : [{ id: null, description: '', quantity: 1, unit_price_cents: 0 }],
})

function addRow() {
  form.items = [...form.items, { id: null, description: '', quantity: 1, unit_price_cents: 0 }]
}

function removeRow(index: number) {
  form.items = form.items.filter((_, i) => i !== index)
  if (form.items.length === 0) {
    form.items = [{ id: null, description: '', quantity: 1, unit_price_cents: 0 }]
  }
}

const totalCents = computed(() => {
  return form.items.reduce((sum, row) => {
    const qty = Number(row.quantity) || 0
    const price = Number(row.unit_price_cents) || 0
    return sum + Math.round(qty * price)
  }, 0)
})

function formatMoney(cents: number) {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: props.invoice.currency || 'USD',
  }).format(cents / 100)
}

function saveInvoice() {
  const payload = {
    issue_date: form.issue_date,
    due_date: form.due_date,
    notes: form.notes,
    items: form.items.map((i) => ({
      id: i.id ?? undefined,
      description: i.description,
      quantity: Number(i.quantity) || 0,
      unit_price_cents: Number(i.unit_price_cents) || 0,
    })),
  }
  form.transform(() => payload).put(r('invoices.update', { organization: props.organizationSlug, invoice: props.invoice.id }), {
    preserveScroll: true,
  })
}

function markSent() {
  router.post(r('invoices.mark-sent', { organization: props.organizationSlug, invoice: props.invoice.id }), {}, { preserveScroll: true })
}

function markPaid() {
  router.post(r('invoices.mark-paid', { organization: props.organizationSlug, invoice: props.invoice.id }), {}, { preserveScroll: true })
}

const downloadUrl = computed(() => r('invoices.download', { organization: props.organizationSlug, invoice: props.invoice.id }))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <Link :href="r('invoices.index', { organization: organizationSlug })" class="text-white/60 hover:text-white text-sm">← Invoices</Link>
        <h1 class="text-2xl font-semibold text-white">Invoice {{ invoice.number }}</h1>
        <span
          :class="[
            'rounded-full px-2.5 py-0.5 text-xs font-medium',
            invoice.status === 'Draft' && 'bg-amber-900/50 text-amber-300',
            invoice.status === 'Sent' && 'bg-blue-900/50 text-blue-300',
            invoice.status === 'Paid' && 'bg-emerald-900/50 text-emerald-300',
            invoice.status === 'Overdue' && 'bg-rose-900/50 text-rose-300',
          ]"
        >
          {{ invoice.status }}
        </span>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a
          :href="downloadUrl"
          target="_blank"
          rel="noopener"
          class="rounded-xl border border-white/20 bg-white/5 px-4 py-2 text-sm text-white/90 hover:bg-white/10"
        >
          Download PDF
        </a>
        <button
          v-if="invoice.status === 'Draft'"
          type="button"
          class="rounded-xl bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
          @click="markSent"
        >
          Mark as Sent
        </button>
        <button
          v-if="invoice.status === 'Sent' || invoice.status === 'Draft'"
          type="button"
          class="rounded-xl bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700"
          @click="markPaid"
        >
          Mark as Paid
        </button>
      </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 space-y-6">
      <p class="text-white/70">
        <strong>Client:</strong> {{ invoice.client.company_name }}
        <template v-if="invoice.project"> · <strong>Project:</strong> {{ invoice.project.title }}</template>
      </p>

      <div class="grid grid-cols-2 gap-4 max-w-md">
        <div>
          <label class="block text-xs font-medium text-white/50 mb-1">Issue date</label>
          <input v-model="form.issue_date" type="date" :class="inputClass" />
        </div>
        <div>
          <label class="block text-xs font-medium text-white/50 mb-1">Due date</label>
          <input v-model="form.due_date" type="date" :class="inputClass" />
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-white/50 mb-1">Notes</label>
        <textarea v-model="form.notes" rows="2" :class="inputClass" placeholder="Optional notes" />
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm font-medium text-white/80">Line items</label>
          <button type="button" class="text-sm text-indigo-300 hover:text-indigo-200" @click="addRow">
            + Add row
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-white/50 border-b border-white/10">
                <th class="pb-2 pr-2">Description</th>
                <th class="pb-2 pr-2 w-24">Qty</th>
                <th class="pb-2 pr-2 w-32">Unit price (cents)</th>
                <th class="pb-2 w-28 text-right">Amount</th>
                <th class="w-10" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in form.items" :key="index" class="border-b border-white/5">
                <td class="py-2 pr-2">
                  <input
                    v-model="row.description"
                    type="text"
                    placeholder="Description"
                    :class="inputClass"
                  />
                </td>
                <td class="py-2 pr-2">
                  <input
                    v-model.number="row.quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    :class="inputClass"
                  />
                </td>
                <td class="py-2 pr-2">
                  <input
                    v-model.number="row.unit_price_cents"
                    type="number"
                    min="0"
                    :class="inputClass"
                  />
                </td>
                <td class="py-2 pr-2 text-right text-white/80">
                  {{ formatMoney(Math.round((Number(row.quantity) || 0) * (Number(row.unit_price_cents) || 0))) }}
                </td>
                <td class="py-2">
                  <button
                    type="button"
                    class="text-white/50 hover:text-rose-400 p-1"
                    title="Remove row"
                    @click="removeRow(index)"
                  >
                    ×
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="mt-3 text-sm text-white/60">
          Total: <strong class="text-white">{{ formatMoney(totalCents) }}</strong>
        </p>
      </div>

      <div class="flex gap-3 pt-4">
        <button
          type="button"
          class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="form.processing"
          @click="saveInvoice"
        >
          {{ form.processing ? 'Saving…' : 'Save changes' }}
        </button>
      </div>
    </div>
  </div>
</template>
