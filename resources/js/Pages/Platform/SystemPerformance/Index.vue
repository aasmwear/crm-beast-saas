<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Card from '@/Components/ui/Card.vue';

const route = (window as any).route;

interface Check {
  ok: boolean;
  driver: string;
  error?: string;
}

interface Readiness {
  status: string;
  checks: Record<string, Check>;
  timestamp: string;
}

interface QueueHealth {
  total_failed: number;
  failed_last_24h: number;
  failed_last_7d: number;
  queue_driver: string;
  recent_failures: Array<{ id: number; queue: string; failed_at: string }>;
}

interface WebhookHealth {
  total_events: number;
  processed_count: number;
  failed_count: number;
  failed_last_24h: number;
  failed_last_7d: number;
  orgs_with_failures_7d: number;
  recent_failures: Array<{
    id: number;
    stripe_event_id: string;
    type: string;
    status: string;
    organization_id: number | null;
    created_at: string;
  }>;
}

interface StoragePressureOrg {
  id: number;
  name: string;
  slug: string;
  used_gb: number;
  limit_gb: number;
  pct_used: number;
  over_limit: boolean;
}

interface StoragePressure {
  orgs_over_limit: number;
  orgs_near_limit: number;
  total_storage_used_gb: number;
  details: StoragePressureOrg[];
}

interface AtRiskOrgs {
  billing_at_risk: number;
  webhook_at_risk: number;
  past_due_count: number;
  unpaid_count: number;
  orgs_3plus_webhook_failures: number;
}

interface PlatformSummary {
  total_orgs: number;
  total_users: number;
  active_subscriptions: number;
  stripe_linked: number;
}

const props = defineProps<{
  readiness: Readiness;
  queue_health: QueueHealth;
  webhook_health: WebhookHealth;
  storage_pressure: StoragePressure;
  at_risk_orgs: AtRiskOrgs;
  platform_summary: PlatformSummary;
}>();

const readinessColor = computed(() =>
  props.readiness.status === 'healthy'
    ? 'text-emerald-400'
    : 'text-red-400'
);

const readinessBg = computed(() =>
  props.readiness.status === 'healthy'
    ? 'from-emerald-600/20 to-green-600/20 border-emerald-500/30'
    : 'from-red-600/20 to-orange-600/20 border-red-500/30'
);

const checkColor = (ok: boolean) => ok ? 'text-emerald-400' : 'text-red-400';
const checkBadge = (ok: boolean) => ok
  ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
  : 'bg-red-500/20 text-red-400 border-red-500/30';

const totalAtRisk = computed(() =>
  props.at_risk_orgs.billing_at_risk + props.at_risk_orgs.webhook_at_risk
);

const formatNum = (n: number) => n.toLocaleString();

const timeAgo = (iso: string): string => {
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
};

const webhookSuccessRate = computed(() => {
  if (props.webhook_health.total_events === 0) return 100;
  return Math.round(
    (props.webhook_health.processed_count / props.webhook_health.total_events) * 100
  );
});

const storagePressureColor = (pct: number) => {
  if (pct >= 100) return 'text-red-400';
  if (pct >= 90) return 'text-amber-400';
  return 'text-white';
};

const storagePressureBarColor = (pct: number) => {
  if (pct >= 100) return 'bg-red-500';
  if (pct >= 90) return 'bg-amber-500';
  return 'bg-blue-500';
};
</script>

<template>
  <Head title="System Performance — Platform" />

  <PlatformLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white mb-2">System Performance</h1>
      <p class="text-white/60">
        Infrastructure readiness, queue health, webhook reliability, and storage pressure
      </p>
    </div>

    <!-- Readiness + Summary Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">System Status</p>
        <div class="flex items-center gap-2">
          <span
            class="w-3 h-3 rounded-full"
            :class="readiness.status === 'healthy' ? 'bg-emerald-400' : 'bg-red-400'"
          />
          <p class="text-2xl font-bold capitalize" :class="readinessColor">
            {{ readiness.status }}
          </p>
        </div>
        <p class="text-xs text-white/40 mt-1">DB + Cache + Queue</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Failed Jobs (24h)</p>
        <p class="text-2xl font-bold" :class="queue_health.failed_last_24h > 0 ? 'text-amber-400' : 'text-emerald-400'">
          {{ formatNum(queue_health.failed_last_24h) }}
        </p>
        <p class="text-xs text-white/40 mt-1">{{ formatNum(queue_health.total_failed) }} total · {{ queue_health.queue_driver }} driver</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Webhook Failures (7d)</p>
        <p class="text-2xl font-bold" :class="webhook_health.failed_last_7d > 0 ? 'text-amber-400' : 'text-emerald-400'">
          {{ formatNum(webhook_health.failed_last_7d) }}
        </p>
        <p class="text-xs text-white/40 mt-1">{{ webhookSuccessRate }}% success rate · {{ formatNum(webhook_health.total_events) }} total</p>
      </Card>

      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">At-Risk Orgs</p>
        <p class="text-2xl font-bold" :class="totalAtRisk > 0 ? 'text-red-400' : 'text-emerald-400'">
          {{ formatNum(totalAtRisk) }}
        </p>
        <p class="text-xs text-white/40 mt-1">
          {{ at_risk_orgs.billing_at_risk }} billing · {{ at_risk_orgs.webhook_at_risk }} webhook
        </p>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Readiness Detail -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-1">Infrastructure Readiness</h2>
        <p class="text-xs text-white/40 mb-5">Live check of database, cache, and queue subsystems</p>

        <div class="space-y-3">
          <div
            v-for="(check, name) in readiness.checks"
            :key="name"
            class="flex items-center justify-between py-3 px-4 rounded-lg bg-white/5 border border-white/10"
          >
            <div class="flex items-center gap-3">
              <span
                class="w-2.5 h-2.5 rounded-full"
                :class="check.ok ? 'bg-emerald-400' : 'bg-red-400'"
              />
              <span class="text-sm font-medium text-white capitalize">{{ name }}</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs text-white/50">{{ check.driver }}</span>
              <span
                class="text-xs px-2 py-0.5 rounded-full border font-medium"
                :class="checkBadge(check.ok)"
              >
                {{ check.ok ? 'OK' : check.error || 'FAIL' }}
              </span>
            </div>
          </div>
        </div>

        <p class="text-xs text-white/30 mt-4">
          Checked at {{ new Date(readiness.timestamp).toLocaleTimeString() }}
        </p>
      </Card>

      <!-- Queue Health Detail -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-1">Queue Health</h2>
        <p class="text-xs text-white/40 mb-5">Failed jobs from the <code class="text-white/60">failed_jobs</code> table</p>

        <div class="grid grid-cols-3 gap-4 mb-5">
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold" :class="queue_health.failed_last_24h > 0 ? 'text-amber-400' : 'text-emerald-400'">
              {{ formatNum(queue_health.failed_last_24h) }}
            </p>
            <p class="text-xs text-white/50 mt-0.5">Last 24h</p>
          </div>
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold" :class="queue_health.failed_last_7d > 0 ? 'text-amber-400' : 'text-white'">
              {{ formatNum(queue_health.failed_last_7d) }}
            </p>
            <p class="text-xs text-white/50 mt-0.5">Last 7d</p>
          </div>
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold text-white">{{ formatNum(queue_health.total_failed) }}</p>
            <p class="text-xs text-white/50 mt-0.5">All time</p>
          </div>
        </div>

        <div v-if="queue_health.recent_failures.length > 0">
          <h3 class="text-xs uppercase tracking-wider text-white/50 mb-2">Recent failures</h3>
          <div class="space-y-2">
            <div
              v-for="failure in queue_health.recent_failures"
              :key="failure.id"
              class="flex items-center justify-between py-2 px-3 rounded bg-white/5 text-xs"
            >
              <span class="text-white/80 font-mono">{{ failure.queue }}</span>
              <span class="text-white/40">{{ timeAgo(failure.failed_at) }}</span>
            </div>
          </div>
        </div>
        <div v-else class="py-6 text-center text-white/30 text-sm">
          No failed jobs
        </div>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Webhook Health Detail -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-1">Webhook Reliability</h2>
        <p class="text-xs text-white/40 mb-5">Stripe webhook event processing health</p>

        <div class="grid grid-cols-3 gap-4 mb-5">
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold text-emerald-400">{{ formatNum(webhook_health.processed_count) }}</p>
            <p class="text-xs text-white/50 mt-0.5">Processed</p>
          </div>
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold" :class="webhook_health.failed_count > 0 ? 'text-red-400' : 'text-white'">
              {{ formatNum(webhook_health.failed_count) }}
            </p>
            <p class="text-xs text-white/50 mt-0.5">Failed (all)</p>
          </div>
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold" :class="webhook_health.orgs_with_failures_7d > 0 ? 'text-amber-400' : 'text-emerald-400'">
              {{ formatNum(webhook_health.orgs_with_failures_7d) }}
            </p>
            <p class="text-xs text-white/50 mt-0.5">Orgs affected (7d)</p>
          </div>
        </div>

        <div v-if="webhook_health.recent_failures.length > 0">
          <h3 class="text-xs uppercase tracking-wider text-white/50 mb-2">Recent webhook failures</h3>
          <div class="overflow-hidden rounded-lg border border-white/10">
            <table class="w-full">
              <thead>
                <tr class="bg-white/5 border-b border-white/10">
                  <th class="px-3 py-2 text-left text-xs font-medium text-white/60">Event Type</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-white/60">Event ID</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-white/60">When</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr v-for="f in webhook_health.recent_failures" :key="f.id" class="hover:bg-white/5">
                  <td class="px-3 py-2 text-xs text-white/80 font-mono">{{ f.type }}</td>
                  <td class="px-3 py-2 text-xs text-white/50 font-mono truncate max-w-[140px]">{{ f.stripe_event_id }}</td>
                  <td class="px-3 py-2 text-xs text-white/40 text-right">{{ timeAgo(f.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="py-6 text-center text-white/30 text-sm">
          No failed webhook events
        </div>
      </Card>

      <!-- Storage Pressure -->
      <Card class="card-neo">
        <h2 class="text-lg font-bold text-white mb-1">Storage Pressure</h2>
        <p class="text-xs text-white/40 mb-5">Orgs near or over their storage limits</p>

        <div class="grid grid-cols-3 gap-4 mb-5">
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold" :class="storage_pressure.orgs_over_limit > 0 ? 'text-red-400' : 'text-emerald-400'">
              {{ formatNum(storage_pressure.orgs_over_limit) }}
            </p>
            <p class="text-xs text-white/50 mt-0.5">Over limit</p>
          </div>
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold" :class="storage_pressure.orgs_near_limit > 0 ? 'text-amber-400' : 'text-white'">
              {{ formatNum(storage_pressure.orgs_near_limit) }}
            </p>
            <p class="text-xs text-white/50 mt-0.5">Near limit (≥90%)</p>
          </div>
          <div class="text-center p-3 rounded-lg bg-white/5 border border-white/10">
            <p class="text-xl font-bold text-white">{{ storage_pressure.total_storage_used_gb }} GB</p>
            <p class="text-xs text-white/50 mt-0.5">Total used</p>
          </div>
        </div>

        <div v-if="storage_pressure.details.length > 0">
          <h3 class="text-xs uppercase tracking-wider text-white/50 mb-2">Orgs with storage pressure</h3>
          <div class="space-y-3">
            <div
              v-for="org in storage_pressure.details"
              :key="org.id"
              class="py-2 px-3 rounded-lg bg-white/5 border border-white/10"
            >
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm font-medium text-white">{{ org.name }}</span>
                <span class="text-xs font-medium" :class="storagePressureColor(org.pct_used)">
                  {{ org.used_gb }} / {{ org.limit_gb }} GB ({{ org.pct_used }}%)
                </span>
              </div>
              <div class="h-1.5 rounded-full overflow-hidden bg-white/5">
                <div
                  :class="storagePressureBarColor(org.pct_used)"
                  :style="{ width: Math.min(org.pct_used, 100) + '%' }"
                  class="h-full rounded-full transition-all duration-500"
                />
              </div>
              <div class="flex items-center gap-2 mt-1">
                <span v-if="org.over_limit" class="text-xs px-1.5 py-0.5 rounded bg-red-500/20 text-red-400 border border-red-500/30">Over limit</span>
                <span v-else class="text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30">Near limit</span>
                <span class="text-xs text-white/30">{{ org.slug }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="py-6 text-center text-white/30 text-sm">
          No orgs under storage pressure
        </div>
      </Card>
    </div>

    <!-- At-Risk Orgs Detail -->
    <Card class="card-neo mb-8">
      <h2 class="text-lg font-bold text-white mb-1">Operational Risk Summary</h2>
      <p class="text-xs text-white/40 mb-5">Orgs with billing or webhook issues requiring attention</p>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 rounded-lg bg-white/5 border border-white/10">
          <p class="text-2xl font-bold" :class="at_risk_orgs.past_due_count > 0 ? 'text-red-400' : 'text-emerald-400'">
            {{ formatNum(at_risk_orgs.past_due_count) }}
          </p>
          <p class="text-xs text-white/50 mt-1">Past Due</p>
        </div>
        <div class="text-center p-4 rounded-lg bg-white/5 border border-white/10">
          <p class="text-2xl font-bold" :class="at_risk_orgs.unpaid_count > 0 ? 'text-red-400' : 'text-emerald-400'">
            {{ formatNum(at_risk_orgs.unpaid_count) }}
          </p>
          <p class="text-xs text-white/50 mt-1">Unpaid</p>
        </div>
        <div class="text-center p-4 rounded-lg bg-white/5 border border-white/10">
          <p class="text-2xl font-bold" :class="at_risk_orgs.orgs_3plus_webhook_failures > 0 ? 'text-amber-400' : 'text-emerald-400'">
            {{ formatNum(at_risk_orgs.orgs_3plus_webhook_failures) }}
          </p>
          <p class="text-xs text-white/50 mt-1">3+ Webhook Failures (7d)</p>
        </div>
        <div class="text-center p-4 rounded-lg bg-white/5 border border-white/10">
          <p class="text-2xl font-bold text-white">{{ formatNum(platform_summary.total_orgs) }}</p>
          <p class="text-xs text-white/50 mt-1">Total Orgs</p>
        </div>
      </div>

      <div class="mt-5 pt-4 border-t border-white/5 grid grid-cols-3 gap-4 text-center">
        <div>
          <p class="text-lg font-bold text-white">{{ formatNum(platform_summary.total_users) }}</p>
          <p class="text-xs text-white/40">Total Users</p>
        </div>
        <div>
          <p class="text-lg font-bold text-emerald-400">{{ formatNum(platform_summary.active_subscriptions) }}</p>
          <p class="text-xs text-white/40">Active Subscriptions</p>
        </div>
        <div>
          <p class="text-lg font-bold text-purple-400">{{ formatNum(platform_summary.stripe_linked) }}</p>
          <p class="text-xs text-white/40">Stripe-linked</p>
        </div>
      </div>
    </Card>

    <!-- Quick Links -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Link :href="route('platform.organizations.health')" class="block">
        <Card class="card-neo hover:border-purple-500/30 transition group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-600/20 to-red-600/20 border border-orange-500/30 flex items-center justify-center">
              <svg class="w-4 h-4 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-bold text-white">Org Health</h3>
              <p class="text-xs text-white/50 truncate">Per-org health signals</p>
            </div>
            <span class="text-purple-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </Card>
      </Link>

      <Link :href="route('platform.organizations.subscriptions')" class="block">
        <Card class="card-neo hover:border-purple-500/30 transition group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-600/20 to-blue-600/20 border border-purple-500/30 flex items-center justify-center">
              <svg class="w-4 h-4 text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-bold text-white">Org Subscriptions</h3>
              <p class="text-xs text-white/50 truncate">Billing &amp; overrides</p>
            </div>
            <span class="text-purple-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </Card>
      </Link>

      <Link :href="route('platform.revenue')" class="block">
        <Card class="card-neo hover:border-green-500/30 transition group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-green-600/20 to-emerald-600/20 border border-green-500/30 flex items-center justify-center">
              <svg class="w-4 h-4 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-bold text-white">Revenue</h3>
              <p class="text-xs text-white/50 truncate">MRR &amp; subscriptions</p>
            </div>
            <span class="text-green-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </Card>
      </Link>

      <Link :href="route('platform.feature-usage')" class="block">
        <Card class="card-neo hover:border-blue-500/30 transition group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600/20 to-cyan-600/20 border border-blue-500/30 flex items-center justify-center">
              <svg class="w-4 h-4 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 20V10M12 20V4M6 20v-6"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-bold text-white">Feature Usage</h3>
              <p class="text-xs text-white/50 truncate">Module adoption</p>
            </div>
            <span class="text-blue-400 group-hover:translate-x-1 transition-transform">&rarr;</span>
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
