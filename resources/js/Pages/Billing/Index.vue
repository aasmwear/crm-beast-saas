<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

type Invoice = {
  id: number
  date: string
  invoice_number: string
  amount: number
  status: string
  pdf_url: string
}

type Plan = {
  id: string
  name: string
  price: number
  interval: string
  features: string[]
  recommended: boolean
}

const props = defineProps<{
  organization: { id: number; slug: string; name: string }
  currentPlan: string
  nextPayment: string | null
  trialEndsAt: string | null
  invoices: Invoice[]
  plans: Plan[]
  hasActiveSubscription?: boolean
  stripeConfigured?: boolean
}>()

const page = usePage()
const org = computed(() => props.organization?.slug ?? 'acme')

const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, string> = {}) =>
  routeGlobal ? routeGlobal(name, params) : '#'

const trialEndDate = computed(() => (props.trialEndsAt ? new Date(props.trialEndsAt) : null))
const today = computed(() => new Date())
const daysRemaining = computed(() => {
  if (!trialEndDate.value) return 0
  const diff = trialEndDate.value.getTime() - today.value.getTime()
  return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
})
const trialTotalDays = 30
const trialProgressPercent = computed(() =>
  Math.min(100, Math.round(((trialTotalDays - daysRemaining.value) / trialTotalDays) * 100))
)

const processingCheckout = ref(false)

async function handleUpgrade(): Promise<void> {
  if (!props.stripeConfigured || processingCheckout.value) return
  processingCheckout.value = true
  try {
    const res = await fetch(r('billing.checkout', { organization: org.value }), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    })
    const data = await res.json()
    if (data.url) {
      window.location.href = data.url
    } else {
      alert(data.error ?? 'Checkout failed')
    }
  } catch (e) {
    alert('Checkout failed')
  } finally {
    processingCheckout.value = false
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

function statusClass(status: string): string {
  if (status === 'Paid') {
    return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40'
  }
  if (status === 'Pending') {
    return 'bg-amber-500/15 text-amber-300 border border-amber-500/40'
  }
  return 'bg-white/10 text-white/70 border border-white/20'
}
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <section class="hero-slab">
      <div class="flex items-center justify-between gap-4">
        <div>
          <div class="text-xs text-white/60">
            Organization • {{ org.toUpperCase() }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Subscription & Billing
          </h1>
          <p class="mt-1 text-white/60">
            Manage your plan, payment method, and invoice history.
          </p>
        </div>
      </div>
    </section>

    <!-- Section 1: Current Status -->
    <section class="card-neo p-6">
      <h2 class="text-sm font-semibold text-white/80 mb-4">
        Current status
      </h2>
      <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
          <p class="text-2xl font-semibold text-white">
            Current plan: {{ currentPlan }}
          </p>
          <p v-if="nextPayment" class="text-sm text-white/60">
            Next payment: {{ formatDate(nextPayment) }}
          </p>
        </div>
        <div class="flex gap-3 shrink-0">
          <a
            v-if="hasActiveSubscription && stripeConfigured"
            :href="r('billing.portal', { organization: org })"
            class="btn-capsule border border-white/20 bg-white/5 text-white/90 hover:bg-white/10"
          >
            Manage subscription
          </a>
          <button
            v-else-if="!hasActiveSubscription && stripeConfigured"
            type="button"
            class="btn-capsule bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
            :disabled="processingCheckout"
            @click="handleUpgrade"
          >
            {{ processingCheckout ? 'Redirecting...' : 'Upgrade to Pro' }}
          </button>
        </div>
      </div>

      <!-- Trial progress -->
      <div v-if="currentPlan === 'Free Trial'" class="mt-6 pt-6 border-t border-white/10">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-white/60">Days remaining in trial</span>
          <span class="font-medium text-white">{{ daysRemaining }} days</span>
        </div>
        <div class="h-2 w-full rounded-full bg-white/10 overflow-hidden">
          <div
            class="h-full rounded-full bg-violet-600 transition-all duration-500"
            :style="{ width: `${trialProgressPercent}%` }"
          />
        </div>
      </div>
    </section>

    <!-- Section 2: Available Plans -->
    <section>
      <h2 class="text-sm font-semibold text-white/80 mb-4">
        Available plans
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="rounded-2xl border transition overflow-hidden"
          :class="
            plan.recommended
              ? 'border-violet-500/50 bg-violet-500/5 ring-1 ring-violet-500/30'
              : 'border-white/10 bg-slate-900/50 hover:border-white/20'
          "
        >
          <div class="p-6">
            <div v-if="plan.recommended" class="mb-3">
              <span class="inline-flex items-center rounded-full bg-violet-600/20 px-3 py-1 text-xs font-medium text-violet-300">
                Recommended
              </span>
            </div>
            <h3 class="text-lg font-semibold text-white">
              {{ plan.name }}
            </h3>
            <div class="mt-4 flex items-baseline gap-1">
              <span class="text-3xl font-bold text-white">{{ formatCurrency(plan.price) }}</span>
              <span class="text-white/50 text-sm">/ {{ plan.interval }}</span>
            </div>
            <ul class="mt-6 space-y-3">
              <li
                v-for="(feature, i) in plan.features"
                :key="i"
                class="flex items-center gap-2 text-sm text-white/80"
              >
                <svg
                  class="h-5 w-5 shrink-0 text-emerald-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                {{ feature }}
              </li>
            </ul>
            <button
              v-if="plan.id === 'pro' && !hasActiveSubscription && stripeConfigured"
              type="button"
              class="mt-6 w-full rounded-xl py-2.5 text-sm font-semibold transition bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
              :disabled="processingCheckout"
              @click="handleUpgrade"
            >
              {{ processingCheckout ? 'Redirecting...' : 'Upgrade to Pro' }}
            </button>
            <span
              v-else-if="plan.id === 'pro' && hasActiveSubscription"
              class="mt-6 inline-block w-full rounded-xl py-2.5 text-center text-sm font-semibold text-white/60"
            >
              Current plan
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 3: Invoice History -->
    <section class="card-neo overflow-hidden">
      <h2 class="text-sm font-semibold text-white/80 px-6 pt-6 pb-4">
        Invoice history
      </h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wide text-white/60">
            <tr>
              <th class="px-6 py-3">Date</th>
              <th class="px-6 py-3">Invoice #</th>
              <th class="px-6 py-3">Amount</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr
              v-for="inv in invoices"
              :key="inv.id"
              class="hover:bg-white/[0.02] transition"
            >
              <td class="px-6 py-4 text-white/80">
                {{ formatDate(inv.date) }}
              </td>
              <td class="px-6 py-4 font-mono text-white/90">
                {{ inv.invoice_number }}
              </td>
              <td class="px-6 py-4 text-white/90">
                {{ formatCurrency(inv.amount) }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                  :class="statusClass(inv.status)"
                >
                  {{ inv.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <a
                  :href="inv.pdf_url"
                  class="text-violet-400 hover:text-violet-300 text-xs font-medium"
                >
                  Download PDF
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!invoices.length" class="px-6 py-8 text-center text-sm text-white/50">
        No invoices yet.
      </p>
    </section>
  </div>
</template>
