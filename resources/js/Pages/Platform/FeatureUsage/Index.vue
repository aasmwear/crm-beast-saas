<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Card from '@/Components/ui/Card.vue';

const route = (window as any).route;

interface AdoptionMetric {
  orgs_with_any: number;
  total_records: number;
  label: string;
}

interface BillingSetup {
  stripe_linked: number;
  has_subscription: number;
  has_active_subscription: number;
  has_active_addons: number;
}

interface Summary {
  total_orgs: number;
  orgs_using_any_module: number;
  avg_modules_per_org: number;
  modules_tracked: number;
}

const props = defineProps<{
  total_orgs: number;
  adoption: Record<string, AdoptionMetric>;
  billing_setup: BillingSetup;
  summary: Summary;
}>();

const pct = (count: number) => {
  if (props.total_orgs === 0) return 0;
  return Math.round((count / props.total_orgs) * 100);
};

const moduleIcon = (key: string): string => {
  const map: Record<string, string> = {
    clients: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2',
    projects: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    tasks: 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11',
    attendance: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    invoices: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  };
  return map[key] ?? 'M4 6h16M4 12h16M4 18h16';
};

const moduleColor = (key: string): string => {
  const map: Record<string, string> = {
    clients: 'from-blue-600/20 to-cyan-600/20 border-blue-500/30 text-blue-400',
    projects: 'from-purple-600/20 to-indigo-600/20 border-purple-500/30 text-purple-400',
    tasks: 'from-green-600/20 to-emerald-600/20 border-green-500/30 text-green-400',
    attendance: 'from-amber-600/20 to-orange-600/20 border-amber-500/30 text-amber-400',
    invoices: 'from-pink-600/20 to-rose-600/20 border-pink-500/30 text-pink-400',
  };
  return map[key] ?? 'from-slate-600/20 to-slate-600/20 border-slate-500/30 text-slate-400';
};

const barColor = (key: string): string => {
  const map: Record<string, string> = {
    clients: 'bg-blue-500',
    projects: 'bg-purple-500',
    tasks: 'bg-green-500',
    attendance: 'bg-amber-500',
    invoices: 'bg-pink-500',
  };
  return map[key] ?? 'bg-slate-500';
};

const adoptionKeys = computed(() => Object.keys(props.adoption));

const highestAdoption = computed(() => {
  let max = 0;
  for (const key of adoptionKeys.value) {
    const val = props.adoption[key].orgs_with_any;
    if (val > max) max = val;
  }
  return max;
});

const barWidth = (count: number) => {
  if (highestAdoption.value === 0) return 0;
  return Math.round((count / highestAdoption.value) * 100);
};

const activeModulesPct = computed(() => pct(props.summary.orgs_using_any_module));

const formatNum = (n: number) => n.toLocaleString();
</script>

<template>
  <Head title="Feature Usage — Platform" />

  <PlatformLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white mb-2">Feature Usage Dashboard</h1>
      <p class="text-white/60">
        Module adoption and usage overview across all organizations
      </p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Total Orgs</p>
        <p class="text-2xl font-bold text-white">{{ formatNum(summary.total_orgs) }}</p>
        <p class="text-xs text-white/40 mt-1">Platform-wide</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Using Any Module</p>
        <p class="text-2xl font-bold text-emerald-400">{{ formatNum(summary.orgs_using_any_module) }}</p>
        <p class="text-xs text-white/40 mt-1">{{ activeModulesPct }}% of orgs</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Avg Modules / Org</p>
        <p class="text-2xl font-bold text-blue-400">{{ summary.avg_modules_per_org }}</p>
        <p class="text-xs text-white/40 mt-1">of {{ summary.modules_tracked }} tracked</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Billing Configured</p>
        <p class="text-2xl font-bold text-purple-400">{{ formatNum(billing_setup.has_active_subscription) }}</p>
        <p class="text-xs text-white/40 mt-1">{{ billing_setup.stripe_linked }} Stripe-linked</p>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Module Adoption Breakdown -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-1">Module Adoption</h2>
        <p class="text-xs text-white/40 mb-6">Orgs with at least one record in each module (soft-deleted excluded)</p>

        <div class="space-y-5">
          <div v-for="key in adoptionKeys" :key="key">
            <div class="flex items-center justify-between mb-1.5">
              <div class="flex items-center gap-2.5">
                <div
                  class="w-8 h-8 rounded-lg bg-gradient-to-br border flex items-center justify-center"
                  :class="moduleColor(key)"
                >
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path :d="moduleIcon(key)" />
                  </svg>
                </div>
                <span class="text-sm font-medium text-white">{{ adoption[key].label }}</span>
              </div>
              <div class="text-right">
                <span class="text-sm font-bold text-white">{{ formatNum(adoption[key].orgs_with_any) }}</span>
                <span class="text-xs text-white/40 ml-1">orgs ({{ pct(adoption[key].orgs_with_any) }}%)</span>
              </div>
            </div>
            <div class="h-2 rounded-full overflow-hidden bg-white/5">
              <div
                :class="barColor(key)"
                :style="{ width: barWidth(adoption[key].orgs_with_any) + '%' }"
                class="h-full rounded-full transition-all duration-500"
              />
            </div>
            <p class="text-xs text-white/30 mt-1">{{ formatNum(adoption[key].total_records) }} total records</p>
          </div>
        </div>
      </Card>

      <!-- Billing & Setup Metrics -->
      <div class="space-y-6">
        <Card class="card-neo">
          <h2 class="text-lg font-bold text-white mb-1">Billing Setup</h2>
          <p class="text-xs text-white/40 mb-5">Subscription and payment configuration across orgs</p>

          <div class="space-y-4">
            <div class="flex items-center justify-between py-2 border-b border-white/5">
              <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-purple-500" />
                <span class="text-sm text-white/80">Stripe-linked orgs</span>
              </div>
              <div class="text-right">
                <span class="text-sm font-bold text-white">{{ formatNum(billing_setup.stripe_linked) }}</span>
                <span class="text-xs text-white/40 ml-1">({{ pct(billing_setup.stripe_linked) }}%)</span>
              </div>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-white/5">
              <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-blue-500" />
                <span class="text-sm text-white/80">Any subscription</span>
              </div>
              <div class="text-right">
                <span class="text-sm font-bold text-white">{{ formatNum(billing_setup.has_subscription) }}</span>
                <span class="text-xs text-white/40 ml-1">({{ pct(billing_setup.has_subscription) }}%)</span>
              </div>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-white/5">
              <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-green-500" />
                <span class="text-sm text-white/80">Active / trialing subscription</span>
              </div>
              <div class="text-right">
                <span class="text-sm font-bold text-white">{{ formatNum(billing_setup.has_active_subscription) }}</span>
                <span class="text-xs text-white/40 ml-1">({{ pct(billing_setup.has_active_subscription) }}%)</span>
              </div>
            </div>

            <div class="flex items-center justify-between py-2">
              <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-amber-500" />
                <span class="text-sm text-white/80">Active add-ons</span>
              </div>
              <div class="text-right">
                <span class="text-sm font-bold text-white">{{ formatNum(billing_setup.has_active_addons) }}</span>
                <span class="text-xs text-white/40 ml-1">({{ pct(billing_setup.has_active_addons) }}%)</span>
              </div>
            </div>
          </div>
        </Card>

        <!-- Data Notes -->
        <Card class="card-neo">
          <h2 class="text-sm font-bold text-white/70 mb-3">About this data</h2>
          <ul class="space-y-2 text-xs text-white/50">
            <li class="flex gap-2">
              <span class="text-white/30">•</span>
              <span>"Usage" means the org has at least one non-deleted record in that module.</span>
            </li>
            <li class="flex gap-2">
              <span class="text-white/30">•</span>
              <span>Counts are live from DB — no event tracking or sampling involved.</span>
            </li>
            <li class="flex gap-2">
              <span class="text-white/30">•</span>
              <span>Soft-deleted records (clients, projects, tasks, attendance) are excluded.</span>
            </li>
            <li class="flex gap-2">
              <span class="text-white/30">•</span>
              <span>"Avg modules / org" is the sum of per-module adoption divided by total orgs.</span>
            </li>
          </ul>
        </Card>
      </div>
    </div>

    <!-- Detailed Adoption Table -->
    <Card class="card-neo mb-8">
      <h2 class="text-lg font-bold text-white mb-4">Adoption Detail</h2>

      <div class="overflow-hidden rounded-lg border border-white/10">
        <table class="w-full">
          <thead>
            <tr class="bg-white/5 border-b border-white/10">
              <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Module</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">Orgs Using</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">% of Total</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">Total Records</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">Avg / Org</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            <tr v-for="key in adoptionKeys" :key="key" class="hover:bg-white/5 transition">
              <td class="px-6 py-3">
                <div class="flex items-center gap-2.5">
                  <div
                    class="w-7 h-7 rounded-lg bg-gradient-to-br border flex items-center justify-center"
                    :class="moduleColor(key)"
                  >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path :d="moduleIcon(key)" />
                    </svg>
                  </div>
                  <span class="text-sm font-medium text-white">{{ adoption[key].label }}</span>
                </div>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="text-sm font-medium text-white">{{ formatNum(adoption[key].orgs_with_any) }}</span>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="text-sm text-white/60">{{ pct(adoption[key].orgs_with_any) }}%</span>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="text-sm text-white/60">{{ formatNum(adoption[key].total_records) }}</span>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="text-sm text-white/60">
                  {{ adoption[key].orgs_with_any > 0 ? (adoption[key].total_records / adoption[key].orgs_with_any).toFixed(1) : '—' }}
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
        :href="route('platform.revenue')"
        class="block"
      >
        <Card class="card-neo hover:border-green-500/30 transition group">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-600/20 to-emerald-600/20 border border-green-500/30 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-sm font-bold text-white mb-0.5">Revenue</h3>
              <p class="text-xs text-white/50">MRR &amp; subscription overview</p>
            </div>
            <span class="text-green-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
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
