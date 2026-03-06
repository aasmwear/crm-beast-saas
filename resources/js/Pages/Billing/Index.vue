<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

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
  has_stripe_price: boolean
}

type Subscription = {
  plan_key: string
  status: string
  trial_ends_at: string | null
  current_period_ends_at: string | null
  seats_included: number
  seat_limit: number | null
}

type SeatData = {
  active_count: number
  can_add_seat: boolean
}

type Addon = {
  id?: number
  addon_key: string
  mode: string
  quantity: number
  value_int: number | null
  active: boolean
  starts_at: string | null
  ends_at: string | null
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
  stripeEnabled?: boolean
  subscription?: Subscription
  seats?: SeatData
  entitlements?: Record<string, boolean | number>
  addons?: Addon[]
  canUpdateBilling?: boolean
  canManageBilling?: boolean
  planKeys?: string[]
  addonKeys?: string[]
}>()

const org = computed(() => props.organization?.slug ?? 'acme')
const sub = computed<Subscription | null>(() => props.subscription ?? null)
const seats = computed<SeatData>(() => props.seats ?? { active_count: 0, can_add_seat: false })
const entitlements = computed<Record<string, boolean | number>>(() => props.entitlements ?? {})
const addons = computed<Addon[]>(() => props.addons ?? [])

const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, string> = {}) =>
  routeGlobal ? routeGlobal(name, params) : '#'

function planKeyLabel(key: string): string {
  if (key === 'starter') return 'Starter'
  if (key === 'pro') return 'Pro'
  if (key === 'enterprise') return 'Enterprise'
  return key.charAt(0).toUpperCase() + key.slice(1)
}

const canonicalPlanKey = computed(() => sub.value?.plan_key ?? 'starter')
const canonicalPlanLabel = computed(() => planKeyLabel(canonicalPlanKey.value))

const trialEndDate = computed(() => {
  const t = sub.value?.trial_ends_at ?? props.trialEndsAt
  return t ? new Date(t) : null
})
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

const entitlementEntries = computed(() =>
  Object.entries(entitlements.value).sort(([a], [b]) => a.localeCompare(b))
)

const canUpdateBilling = computed(() => props.canUpdateBilling ?? false)
const canManageBilling = computed(() => props.canManageBilling ?? false)
const planKeys = computed(() => props.planKeys ?? ['starter', 'pro', 'enterprise'])
const addonKeys = computed(() => props.addonKeys ?? ['storage_gb', 'api_rpm'])
const stripeEnabled = computed(() => props.stripeEnabled ?? props.stripeConfigured ?? false)

const processingCheckout = ref(false)
const savingPlan = ref(false)
const showAddonForm = ref(false)
const addonForm = ref({
  addon_key: 'storage_gb',
  mode: 'augment' as 'augment' | 'set',
  quantity: 1,
  value_int: null as number | null,
  active: true,
})
const editingAddon = ref<Addon | null>(null)
const editForm = ref<{ mode: string; quantity: number; value_int: number | null; active: boolean }>({ mode: 'augment', quantity: 1, value_int: null, active: true })

const selectedPlanKey = ref(canonicalPlanKey.value)
watch(canonicalPlanKey, (v) => { selectedPlanKey.value = v })

function savePlan(): void {
  const key = selectedPlanKey.value
  if (!canUpdateBilling.value || savingPlan.value || !key) return
  savingPlan.value = true
  router.patch(r('billing.plan.update', { organization: org.value }), { plan_key: key }, {
    preserveScroll: true,
    onFinish: () => { savingPlan.value = false },
  })
}

function submitAddon(): void {
  if (!canUpdateBilling.value) return
  const payload = {
    addon_key: addonForm.value.addon_key,
    mode: addonForm.value.mode,
    quantity: addonForm.value.quantity,
    value_int: addonForm.value.value_int,
    active: addonForm.value.active,
  }
  router.post(r('billing.addons.store', { organization: org.value }), payload, {
    preserveScroll: true,
    onSuccess: () => {
      showAddonForm.value = false
      addonForm.value = { addon_key: 'storage_gb', mode: 'augment', quantity: 1, value_int: null, active: true }
    },
  })
}

function openEditAddon(a: Addon): void {
  editingAddon.value = a
  editForm.value = {
    mode: a.mode,
    quantity: a.quantity,
    value_int: a.value_int ?? null,
    active: a.active,
  }
}

function submitEditAddon(): void {
  if (!editingAddon.value?.id || !canUpdateBilling.value) return
  router.patch(
    r('billing.addons.update', { organization: org.value, addon: String(editingAddon.value.id) }),
    { mode: editForm.value.mode, quantity: editForm.value.quantity, value_int: editForm.value.value_int, active: editForm.value.active },
    {
      preserveScroll: true,
      onSuccess: () => { editingAddon.value = null },
    },
  )
}

function removeAddon(a: Addon): void {
  if (!a.id || !canUpdateBilling.value || !confirm('Deactivate this add-on?')) return
  router.delete(r('billing.addons.destroy', { organization: org.value, addon: String(a.id) }), {
    preserveScroll: true,
  })
}

async function handlePlanCheckout(planKey: string): Promise<void> {
  if (!stripeEnabled.value || processingCheckout.value || !canManageBilling.value) return
  processingCheckout.value = true
  try {
    const res = await fetch(r('billing.checkout', { organization: org.value }), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ plan_key: planKey }),
    })
    const data = await res.json()
    if (data.url) {
      window.location.href = data.url
    } else if (data.ok) {
      router.reload({ only: ['subscription', 'plans', 'hasActiveSubscription'] })
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
  <PageShell
    :header="{
      breadcrumb: `Organization • ${String(org).toUpperCase()}`,
      title: 'Subscription & Billing',
      subtitle: 'View plan, seats, entitlements, and manage payment.',
    }"
  >
    <div class="space-y-6">
      <!-- Section 1: Current Plan / Status (canonical from backend) -->
      <section class="card-neo p-6">
        <h2 class="text-sm font-semibold text-white/80 mb-4">
          Current plan & status
        </h2>
        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
          <div class="space-y-2">
            <div v-if="canUpdateBilling" class="flex items-center gap-2">
              <span class="text-white/70 text-sm">Plan:</span>
              <select
                v-model="selectedPlanKey"
                class="rounded-lg border border-white/20 bg-white/5 px-3 py-1.5 text-white text-sm focus:border-violet-400 focus:ring-violet-400/30"
              >
                <option
                  v-for="pk in planKeys"
                  :key="pk"
                  :value="pk"
                >
                  {{ planKeyLabel(pk) }}
                </option>
              </select>
              <button
                type="button"
                class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                :disabled="savingPlan || selectedPlanKey === canonicalPlanKey"
                @click="savePlan"
              >
                {{ savingPlan ? 'Saving...' : 'Save plan' }}
              </button>
            </div>
            <p v-else class="text-2xl font-semibold text-white">
              Plan: {{ canonicalPlanLabel }}
            </p>
            <p class="text-sm text-white/60">
              Status: {{ sub?.status ?? 'none' }}
            </p>
            <p v-if="sub?.current_period_ends_at" class="text-sm text-white/60">
              Period ends: {{ formatDate(sub.current_period_ends_at) }}
            </p>
            <p v-else-if="nextPayment" class="text-sm text-white/60">
              Next payment: {{ formatDate(nextPayment) }}
            </p>
          </div>
          <div class="flex gap-3 shrink-0">
            <a
              v-if="hasActiveSubscription && stripeEnabled"
              :href="r('billing.portal', { organization: org })"
              class="btn-capsule border border-white/20 bg-white/5 text-white/90 hover:bg-white/10"
            >
              Manage subscription
            </a>
            <button
              v-else-if="!hasActiveSubscription && stripeEnabled && canManageBilling"
              type="button"
              class="btn-capsule bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
              :disabled="processingCheckout"
              @click="handlePlanCheckout('pro')"
            >
              {{ processingCheckout ? 'Redirecting...' : 'Subscribe to Pro' }}
            </button>
          </div>
        </div>
        <p v-if="!stripeEnabled" class="mt-4 text-xs text-amber-300/90">
          Stripe billing is not configured. Set Stripe keys and plan price IDs to enable self-serve subscriptions.
        </p>

        <!-- Trial progress -->
        <div v-if="trialEndDate" class="mt-6 pt-6 border-t border-white/10">
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

      <!-- Section 2: Seats -->
      <section class="card-neo p-6">
        <h2 class="text-sm font-semibold text-white/80 mb-4">
          Seats
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <p class="text-xs text-white/50 uppercase tracking-wide">Active seats</p>
            <p class="text-xl font-semibold text-white">{{ seats.active_count }}</p>
          </div>
          <div>
            <p class="text-xs text-white/50 uppercase tracking-wide">Seats included</p>
            <p class="text-xl font-semibold text-white">{{ sub?.seats_included ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-white/50 uppercase tracking-wide">Seat limit</p>
            <p class="text-xl font-semibold text-white">{{ sub?.seat_limit ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-white/50 uppercase tracking-wide">Can add seat</p>
            <p class="text-xl font-semibold" :class="seats.can_add_seat ? 'text-emerald-400' : 'text-amber-400'">
              {{ seats.can_add_seat ? 'Yes' : 'No' }}
            </p>
          </div>
        </div>
      </section>

      <!-- Section 3: Effective Entitlements -->
      <section class="card-neo p-6 overflow-hidden">
        <h2 class="text-sm font-semibold text-white/80 mb-4">
          Effective entitlements
        </h2>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wide text-white/60">
              <tr>
                <th class="px-4 py-2">Key</th>
                <th class="px-4 py-2">Value</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr
                v-for="[key, val] in entitlementEntries"
                :key="key"
                class="hover:bg-white/[0.02] transition"
              >
                <td class="px-4 py-3 font-mono text-white/90">{{ key }}</td>
                <td class="px-4 py-3">
                  <span v-if="typeof val === 'boolean'" :class="val ? 'text-emerald-400' : 'text-white/50'">
                    {{ val ? 'Yes' : 'No' }}
                  </span>
                  <span v-else class="text-white/90">{{ val }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="!entitlementEntries.length" class="py-4 text-center text-sm text-white/50">
          No entitlements configured.
        </p>
      </section>

      <!-- Section 4: Add-ons -->
      <section class="card-neo p-6 overflow-hidden">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-white/80">
            Add-ons
          </h2>
          <button
            v-if="canUpdateBilling && !showAddonForm"
            type="button"
            class="rounded-lg border border-white/20 bg-white/5 px-3 py-1.5 text-sm text-white hover:bg-white/10"
            @click="showAddonForm = true"
          >
            + Add add-on
          </button>
        </div>

        <!-- Add-on form -->
        <form
          v-if="showAddonForm && canUpdateBilling"
          class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-white/10 bg-white/5 p-4"
          @submit.prevent="submitAddon"
        >
          <div>
            <label class="block text-xs text-white/60 mb-1">Key</label>
            <select
              v-model="addonForm.addon_key"
              class="rounded border border-white/20 bg-slate-900/50 px-2 py-1.5 text-sm text-white"
              required
            >
              <option v-for="k in addonKeys" :key="k" :value="k">{{ k }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-white/60 mb-1">Mode</label>
            <select
              v-model="addonForm.mode"
              class="rounded border border-white/20 bg-slate-900/50 px-2 py-1.5 text-sm text-white"
            >
              <option value="augment">augment</option>
              <option value="set">set</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-white/60 mb-1">Quantity</label>
            <input
              v-model.number="addonForm.quantity"
              type="number"
              min="1"
              class="rounded border border-white/20 bg-slate-900/50 px-2 py-1.5 text-sm text-white w-20"
            />
          </div>
          <div>
            <label class="block text-xs text-white/60 mb-1">Value (optional)</label>
            <input
              v-model.number="addonForm.value_int"
              type="number"
              min="0"
              placeholder="—"
              class="rounded border border-white/20 bg-slate-900/50 px-2 py-1.5 text-sm text-white w-24"
            />
          </div>
          <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 text-sm text-white/80">
              <input v-model="addonForm.active" type="checkbox" class="rounded" />
              Active
            </label>
          </div>
          <div class="flex gap-2">
            <button
              type="submit"
              class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm text-white hover:bg-violet-700"
            >
              Create
            </button>
            <button
              type="button"
              class="rounded-lg border border-white/20 px-3 py-1.5 text-sm text-white/80 hover:bg-white/10"
              @click="showAddonForm = false"
            >
              Cancel
            </button>
          </div>
        </form>

        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wide text-white/60">
              <tr>
                <th class="px-4 py-2">Key</th>
                <th class="px-4 py-2">Mode</th>
                <th class="px-4 py-2">Quantity</th>
                <th class="px-4 py-2">Value</th>
                <th class="px-4 py-2">Active</th>
                <th class="px-4 py-2">Period</th>
                <th v-if="canUpdateBilling" class="px-4 py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <template v-for="(a, idx) in addons" :key="`addon-${idx}-${a.addon_key}`">
                <tr v-if="editingAddon?.id !== a.id" class="hover:bg-white/[0.02] transition">
                  <td class="px-4 py-3 font-mono text-white/90">{{ a.addon_key }}</td>
                  <td class="px-4 py-3 text-white/80">{{ a.mode }}</td>
                  <td class="px-4 py-3 text-white/80">{{ a.quantity }}</td>
                  <td class="px-4 py-3 text-white/80">{{ a.value_int ?? '—' }}</td>
                  <td class="px-4 py-3">
                    <span :class="a.active ? 'text-emerald-400' : 'text-white/50'">
                      {{ a.active ? 'Yes' : 'No' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-white/60 text-xs">
                    {{ a.starts_at ? formatDate(a.starts_at) : '—' }}
                    <span v-if="a.ends_at"> → {{ formatDate(a.ends_at) }}</span>
                  </td>
                  <td v-if="canUpdateBilling" class="px-4 py-3 text-right">
                    <button
                      type="button"
                      class="text-violet-400 hover:text-violet-300 text-xs font-medium mr-2"
                      @click="openEditAddon(a)"
                    >
                      Edit
                    </button>
                    <button
                      v-if="a.active"
                      type="button"
                      class="text-rose-400 hover:text-rose-300 text-xs font-medium"
                      @click="removeAddon(a)"
                    >
                      Deactivate
                    </button>
                  </td>
                </tr>
                <tr v-else class="bg-white/5">
                  <td colspan="7" class="px-4 py-3">
                    <form class="flex flex-wrap items-center gap-3" @submit.prevent="submitEditAddon">
                      <span class="font-mono text-white/90">{{ a.addon_key }}</span>
                      <select
                        v-model="editForm.mode"
                        class="rounded border border-white/20 bg-slate-900/50 px-2 py-1 text-sm text-white"
                      >
                        <option value="augment">augment</option>
                        <option value="set">set</option>
                      </select>
                      <input
                        v-model.number="editForm.quantity"
                        type="number"
                        min="1"
                        class="rounded border border-white/20 bg-slate-900/50 px-2 py-1 text-sm text-white w-16"
                      />
                      <input
                        v-model.number="editForm.value_int"
                        type="number"
                        min="0"
                        placeholder="—"
                        class="rounded border border-white/20 bg-slate-900/50 px-2 py-1 text-sm text-white w-20"
                      />
                      <label class="flex items-center gap-2 text-sm text-white/80">
                        <input v-model="editForm.active" type="checkbox" class="rounded" />
                        Active
                      </label>
                      <button
                        type="submit"
                        class="rounded bg-violet-600 px-2 py-1 text-xs text-white hover:bg-violet-700"
                      >
                        Save
                      </button>
                      <button
                        type="button"
                        class="rounded border border-white/20 px-2 py-1 text-xs text-white/80"
                        @click="editingAddon = null"
                      >
                        Cancel
                      </button>
                    </form>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <p v-if="!addons.length && !showAddonForm" class="py-4 text-center text-sm text-white/50">
          No add-ons configured.
        </p>
      </section>

      <!-- Section 5: Available Plans -->
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
                v-if="canManageBilling && stripeEnabled && plan.has_stripe_price && plan.id !== canonicalPlanKey"
                type="button"
                class="mt-6 w-full rounded-xl py-2.5 text-sm font-semibold transition bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
                :disabled="processingCheckout"
                @click="handlePlanCheckout(plan.id)"
              >
                {{ processingCheckout ? 'Redirecting...' : (hasActiveSubscription ? 'Switch to plan' : 'Subscribe') }}
              </button>
              <span
                v-else-if="plan.id === canonicalPlanKey"
                class="mt-6 inline-block w-full rounded-xl py-2.5 text-center text-sm font-semibold text-white/60"
              >
                Current plan
              </span>
              <span
                v-else-if="!plan.has_stripe_price"
                class="mt-6 inline-block w-full rounded-xl py-2.5 text-center text-sm font-semibold text-white/50"
              >
                Contact sales
              </span>
              <span
                v-else-if="!canManageBilling"
                class="mt-6 inline-block w-full rounded-xl py-2.5 text-center text-sm font-semibold text-white/50"
              >
                No billing access
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 6: Invoice History -->
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
  </PageShell>
</template>
