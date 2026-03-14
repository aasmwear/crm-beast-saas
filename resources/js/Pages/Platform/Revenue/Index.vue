<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Card from '@/Components/ui/Card.vue';

const route = (window as any).route;

interface PlanMrr {
  count: number;
  price_cents: number;
  subtotal_cents: number;
}

interface MrrBreakdown {
  estimated_total_cents: number;
  by_plan: Record<string, PlanMrr>;
  enterprise_note: string | null;
}

const props = defineProps<{
  total_orgs: number;
  stripe_linked_count: number;
  total_seats: number;
  subscription_counts: Record<string, number>;
  active_revenue_orgs: number;
  at_risk_orgs: number;
  plan_distribution: Record<string, number>;
  mrr: MrrBreakdown;
}>();

const formatCurrency = (cents: number) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(cents / 100);

const formatCurrencyFull = (cents: number) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(cents / 100);

const planLabel = (key: string) => {
  const map: Record<string, string> = { starter: 'Starter', pro: 'Pro', enterprise: 'Enterprise' };
  return map[key] ?? key;
};

const planColor = (key: string) => {
  const map: Record<string, string> = {
    starter: 'bg-slate-500',
    pro: 'bg-purple-500',
    enterprise: 'bg-amber-500',
  };
  return map[key] ?? 'bg-white/30';
};

const planBadgeClass = (key: string) => {
  const map: Record<string, string> = {
    starter: 'bg-slate-600/20 text-slate-400 border-slate-500/30',
    pro: 'bg-purple-600/20 text-purple-400 border-purple-500/30',
    enterprise: 'bg-amber-600/20 text-amber-400 border-amber-500/30',
  };
  return map[key] ?? 'bg-white/10 text-white/60 border-white/20';
};

const statusColor = (status: string) => {
  const map: Record<string, string> = {
    active: 'text-green-400',
    trialing: 'text-blue-400',
    past_due: 'text-amber-400',
    unpaid: 'text-red-400',
    canceled: 'text-red-400',
    incomplete: 'text-slate-400',
    none: 'text-white/50',
  };
  return map[status] ?? 'text-white/50';
};

const statusLabel = (status: string) => {
  const map: Record<string, string> = {
    active: 'Active',
    trialing: 'Trialing',
    past_due: 'Past Due',
    unpaid: 'Unpaid',
    canceled: 'Canceled',
    incomplete: 'Incomplete',
    none: 'No Subscription',
  };
  return map[status] ?? status;
};

const planDistTotal = computed(() =>
  Object.values(props.plan_distribution).reduce((a, b) => a + b, 0)
);

const planBarPercent = (count: number) => {
  if (planDistTotal.value === 0) return 0;
  return Math.round((count / planDistTotal.value) * 100);
};

const statusOrder = ['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'canceled', 'none'];
</script>

<template>
  <Head title="Revenue — Platform" />

  <PlatformLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white mb-2">Revenue Dashboard</h1>
      <p class="text-white/60">Subscription and revenue health overview (estimated from canonical plan data)</p>
    </div>

    <!-- KPI Cards Row 1 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Estimated MRR</p>
        <p class="text-2xl font-bold text-green-400">{{ formatCurrency(mrr.estimated_total_cents) }}</p>
        <p v-if="mrr.enterprise_note" class="text-xs text-amber-400/70 mt-1">{{ mrr.enterprise_note }}</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Total Orgs</p>
        <p class="text-2xl font-bold text-white">{{ total_orgs }}</p>
        <p class="text-xs text-white/40 mt-1">{{ stripe_linked_count }} Stripe-linked</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Active / Trialing</p>
        <p class="text-2xl font-bold text-blue-400">{{ active_revenue_orgs }}</p>
        <p class="text-xs text-white/40 mt-1">
          {{ subscription_counts.active ?? 0 }} active, {{ subscription_counts.trialing ?? 0 }} trialing
        </p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">At Risk</p>
        <p class="text-2xl font-bold" :class="at_risk_orgs > 0 ? 'text-red-400' : 'text-white/50'">
          {{ at_risk_orgs }}
        </p>
        <p class="text-xs text-white/40 mt-1">
          {{ subscription_counts.past_due ?? 0 }} past due, {{ subscription_counts.unpaid ?? 0 }} unpaid
        </p>
      </Card>
    </div>

    <!-- KPI Cards Row 2 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Total Seats</p>
        <p class="text-2xl font-bold text-white">{{ total_seats.toLocaleString() }}</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Canceled</p>
        <p class="text-2xl font-bold" :class="(subscription_counts.canceled ?? 0) > 0 ? 'text-red-400' : 'text-white/50'">
          {{ subscription_counts.canceled ?? 0 }}
        </p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Incomplete</p>
        <p class="text-2xl font-bold text-white/50">{{ subscription_counts.incomplete ?? 0 }}</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">No Subscription</p>
        <p class="text-2xl font-bold text-white/50">{{ subscription_counts.none ?? 0 }}</p>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Plan Distribution -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-4">Plan Distribution</h2>

        <!-- Stacked bar -->
        <div class="h-6 rounded-full overflow-hidden flex mb-4 bg-white/5">
          <template v-for="pk in Object.keys(plan_distribution)" :key="pk">
            <div
              v-if="plan_distribution[pk] > 0"
              :class="planColor(pk)"
              :style="{ width: planBarPercent(plan_distribution[pk]) + '%' }"
              class="h-full transition-all duration-300"
              :title="`${planLabel(pk)}: ${plan_distribution[pk]} (${planBarPercent(plan_distribution[pk])}%)`"
            />
          </template>
        </div>

        <!-- Legend -->
        <div class="space-y-3">
          <div v-for="pk in Object.keys(plan_distribution)" :key="pk" class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div :class="planColor(pk)" class="w-3 h-3 rounded-full" />
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                :class="planBadgeClass(pk)"
              >
                {{ planLabel(pk) }}
              </span>
            </div>
            <div class="text-right">
              <span class="text-sm font-medium text-white">{{ plan_distribution[pk] }}</span>
              <span class="text-xs text-white/40 ml-1">({{ planBarPercent(plan_distribution[pk]) }}%)</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- MRR Breakdown -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-4">Estimated MRR Breakdown</h2>
        <p class="text-xs text-white/40 mb-4">Active + trialing orgs only. Enterprise excluded (custom pricing).</p>

        <div class="space-y-3">
          <div v-for="pk in Object.keys(mrr.by_plan)" :key="pk" class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                :class="planBadgeClass(pk)"
              >
                {{ planLabel(pk) }}
              </span>
              <span class="text-sm text-white/60">
                {{ mrr.by_plan[pk].count }} org{{ mrr.by_plan[pk].count !== 1 ? 's' : '' }}
                <span v-if="mrr.by_plan[pk].price_cents > 0" class="text-white/40">
                  @ {{ formatCurrencyFull(mrr.by_plan[pk].price_cents) }}/mo
                </span>
                <span v-else-if="pk === 'enterprise'" class="text-amber-400/60">custom</span>
                <span v-else class="text-white/40">free</span>
              </span>
            </div>
            <span class="text-sm font-medium text-white">
              {{ formatCurrency(mrr.by_plan[pk].subtotal_cents) }}
            </span>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between">
          <span class="text-sm font-bold text-white/70">Estimated Total</span>
          <span class="text-lg font-bold text-green-400">{{ formatCurrency(mrr.estimated_total_cents) }}</span>
        </div>

        <p v-if="mrr.enterprise_note" class="mt-3 text-xs text-amber-400/70 bg-amber-600/10 border border-amber-500/20 rounded-lg px-3 py-2">
          {{ mrr.enterprise_note }}
        </p>
      </Card>
    </div>

    <!-- Status Breakdown -->
    <Card class="card-neo mb-8">
      <h2 class="text-lg font-bold text-white mb-4">Subscription Status Breakdown</h2>

      <div class="overflow-hidden rounded-lg border border-white/10">
        <table class="w-full">
          <thead>
            <tr class="bg-white/5 border-b border-white/10">
              <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">Count</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">% of Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            <tr v-for="s in statusOrder" :key="s" class="hover:bg-white/5 transition">
              <td class="px-6 py-3">
                <span class="text-sm font-medium" :class="statusColor(s)">{{ statusLabel(s) }}</span>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="text-sm text-white/80">{{ subscription_counts[s] ?? 0 }}</span>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="text-sm text-white/60">
                  {{ total_orgs > 0 ? Math.round(((subscription_counts[s] ?? 0) / total_orgs) * 100) : 0 }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Link
        :href="route('platform.organizations.health')"
        class="block"
      >
        <Card class="card-neo hover:border-purple-500/30 transition group">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-600/20 to-red-600/20 border border-orange-500/30 flex items-center justify-center">
              <svg class="w-5 h-5 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-sm font-bold text-white mb-0.5">Org Health</h3>
              <p class="text-xs text-white/50">Per-org health signals</p>
            </div>
            <span class="text-purple-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </Card>
      </Link>

      <Link
        :href="route('platform.organizations.subscriptions')"
        class="block"
      >
        <Card class="card-neo hover:border-purple-500/30 transition group">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600/20 to-blue-600/20 border border-purple-500/30 flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                <path d="M16 3.13a4 4 0 010 7.75"/>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-sm font-bold text-white mb-0.5">Org Subscriptions</h3>
              <p class="text-xs text-white/50">Billing overrides &amp; details</p>
            </div>
            <span class="text-purple-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </Card>
      </Link>

      <Link
        :href="route('platform.organizations.subscriptions', { status: 'past_due' })"
        class="block"
      >
        <Card class="card-neo hover:border-red-500/30 transition group">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600/20 to-amber-600/20 border border-red-500/30 flex items-center justify-center">
              <svg class="w-5 h-5 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-sm font-bold text-white mb-0.5">Past Due Orgs</h3>
              <p class="text-xs text-white/50">Investigate billing issues</p>
            </div>
            <span class="text-red-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </Card>
      </Link>
    </div>
  </PlatformLayout>
</template>

<style scoped>
.card-neo {
  @apply border-white/10;
}
</style>
