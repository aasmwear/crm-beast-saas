<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Card from '@/Components/ui/Card.vue';

const route = (window as any).route;

interface WebhookInfo {
  last_type: string | null;
  last_processed_at: string | null;
  last_status: string | null;
  recent_failed_count: number;
}

interface StorageInfo {
  used_gb: number;
  limit_gb: number;
  over_limit: boolean;
}

interface OrgRow {
  id: number;
  name: string;
  slug: string;
  has_stripe_id: boolean;
  plan_key: string;
  status: string;
  seats_active: number;
  seats_included: number;
  seat_limit: number | null;
  storage: StorageInfo;
  webhook: WebhookInfo;
  health_flags: Record<string, string>;
  health_state: string;
  subscriptions_url: string;
  tenant_billing_url: string;
}

const props = defineProps<{
  organizations: {
    data: OrgRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  filters: { search?: string; status?: string; health?: string };
  statusOptions: string[];
  healthOptions: string[];
}>();

const summary = computed(() => {
  const orgs = props.organizations.data;
  return {
    total: props.organizations.total,
    critical: orgs.filter(o => o.health_state === 'critical').length,
    warning: orgs.filter(o => o.health_state === 'warning').length,
    healthy: orgs.filter(o => o.health_state === 'healthy').length,
  };
});

const applyFilters = (updates: Partial<{ search: string; status: string; health: string }>) => {
  const params: Record<string, string> = { ...props.filters, ...updates } as Record<string, string>;
  Object.keys(params).forEach((k) => (params[k] === '' || params[k] === undefined) && delete params[k]);
  router.get(route('platform.organizations.health'), params, { preserveState: true });
};

const statusBadgeClass = (status: string) => {
  const map: Record<string, string> = {
    active: 'bg-green-600/20 text-green-400 border-green-500/30',
    trialing: 'bg-blue-600/20 text-blue-400 border-blue-500/30',
    past_due: 'bg-amber-600/20 text-amber-400 border-amber-500/30',
    incomplete: 'bg-slate-600/20 text-slate-400 border-slate-500/30',
    canceled: 'bg-red-600/20 text-red-400 border-red-500/30',
    unpaid: 'bg-red-600/20 text-red-400 border-red-500/30',
    none: 'bg-white/10 text-white/60 border-white/20',
  };
  return map[status] ?? 'bg-white/10 text-white/60 border-white/20';
};

const healthStateBadge = (state: string) => {
  const map: Record<string, { label: string; cls: string }> = {
    healthy: { label: 'Healthy', cls: 'bg-green-600/20 text-green-400 border-green-500/30' },
    warning: { label: 'Warning', cls: 'bg-amber-600/20 text-amber-400 border-amber-500/30' },
    critical: { label: 'Critical', cls: 'bg-red-600/20 text-red-400 border-red-500/30' },
  };
  return map[state] ?? { label: state, cls: 'bg-white/10 text-white/60 border-white/20' };
};

const webhookStatusBadge = (webhook: WebhookInfo) => {
  if (webhook.last_status === 'failed') return { label: 'Failed', cls: 'bg-red-600/20 text-red-400 border-red-500/30' };
  if (webhook.recent_failed_count > 0) return { label: `${webhook.recent_failed_count} failed`, cls: 'bg-amber-600/20 text-amber-400 border-amber-500/30' };
  if (webhook.last_status === 'processed') return { label: 'OK', cls: 'bg-green-600/20 text-green-400 border-green-500/30' };
  if (webhook.last_status === 'received') return { label: 'Received', cls: 'bg-slate-600/20 text-slate-400 border-slate-500/30' };
  return { label: 'None', cls: 'bg-white/10 text-white/40 border-white/20' };
};

const seatUsageClass = (org: OrgRow) => {
  const limit = org.seat_limit ?? org.seats_included;
  if (limit > 0 && org.seats_active >= limit) return 'text-red-400';
  if (limit > 0 && org.seats_active >= Math.round(limit * 0.9)) return 'text-amber-400';
  return 'text-white/80';
};

const storageUsageClass = (s: StorageInfo) => {
  if (s.over_limit) return 'text-red-400';
  if (s.limit_gb > 0 && s.used_gb >= +(s.limit_gb * 0.9).toFixed(2)) return 'text-amber-400';
  return 'text-white/80';
};

const formatRelativeTime = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  const now = new Date();
  const sec = Math.floor((now.getTime() - d.getTime()) / 1000);
  if (sec < 60) return 'just now';
  if (sec < 3600) return `${Math.floor(sec / 60)}m ago`;
  if (sec < 86400) return `${Math.floor(sec / 3600)}h ago`;
  return `${Math.floor(sec / 86400)}d ago`;
};

const flagLabels: Record<string, string> = {
  billing: 'Billing',
  seats: 'Seats',
  storage: 'Storage',
  webhooks: 'Webhooks',
};
</script>

<template>
  <Head title="Org Health — Platform" />

  <PlatformLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white mb-2">Org Health Dashboard</h1>
      <p class="text-white/60">Operational health overview across all tenant organizations</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-white/50 mb-1">Total Orgs</p>
        <p class="text-2xl font-bold text-white">{{ summary.total }}</p>
      </Card>
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-green-400/70 mb-1">Healthy</p>
        <p class="text-2xl font-bold text-green-400">{{ summary.healthy }}</p>
      </Card>
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-amber-400/70 mb-1">Warning</p>
        <p class="text-2xl font-bold text-amber-400">{{ summary.warning }}</p>
      </Card>
      <Card class="card-neo">
        <p class="text-xs uppercase tracking-wider text-red-400/70 mb-1">Critical</p>
        <p class="text-2xl font-bold text-red-400">{{ summary.critical }}</p>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="card-neo mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-white/50 mb-1">Search</label>
          <input
            type="text"
            :value="filters.search"
            placeholder="Org name or slug..."
            class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            @keyup.enter="applyFilters({ search: (($event.target as HTMLInputElement)?.value || '').trim() || undefined })"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-white/50 mb-1">Billing Status</label>
          <select
            :value="filters.status ?? ''"
            class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            @change="applyFilters({ status: (($event.target as HTMLSelectElement)?.value || '') || undefined })"
          >
            <option value="">All</option>
            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-white/50 mb-1">Health</label>
          <select
            :value="filters.health ?? ''"
            class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            @change="applyFilters({ health: (($event.target as HTMLSelectElement)?.value || '') || undefined })"
          >
            <option value="">All</option>
            <option v-for="h in healthOptions" :key="h" :value="h">{{ h.charAt(0).toUpperCase() + h.slice(1) }}</option>
          </select>
        </div>
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 hover:bg-purple-600/30 transition"
          @click="applyFilters({ search: undefined, status: undefined, health: undefined })"
        >
          Clear
        </button>
      </div>
    </Card>

    <!-- Table -->
    <Card class="card-neo">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-white/5 border-b border-white/10">
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Organization</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Health</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Plan / Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Stripe</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Seats</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Storage</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Webhooks</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Flags</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            <tr
              v-for="org in organizations.data"
              :key="org.id"
              class="hover:bg-white/5 transition"
            >
              <!-- Org -->
              <td class="px-4 py-3">
                <div>
                  <div class="text-sm font-medium text-white">{{ org.name }}</div>
                  <div class="text-xs text-white/50 font-mono">{{ org.slug }}</div>
                </div>
              </td>

              <!-- Health State -->
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                  :class="healthStateBadge(org.health_state).cls"
                >
                  {{ healthStateBadge(org.health_state).label }}
                </span>
              </td>

              <!-- Plan / Status -->
              <td class="px-4 py-3">
                <div class="text-sm text-white/80">{{ org.plan_key }}</div>
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border mt-0.5"
                  :class="statusBadgeClass(org.status)"
                >
                  {{ org.status }}
                </span>
              </td>

              <!-- Stripe -->
              <td class="px-4 py-3">
                <span v-if="org.has_stripe_id" class="text-xs text-green-400">Linked</span>
                <span v-else class="text-xs text-white/40">—</span>
              </td>

              <!-- Seats -->
              <td class="px-4 py-3">
                <span class="text-sm" :class="seatUsageClass(org)">
                  {{ org.seats_active }} / {{ org.seat_limit ?? org.seats_included }}
                </span>
                <div v-if="org.seat_limit != null" class="text-xs text-white/40">
                  ({{ org.seats_included }} incl, {{ org.seat_limit }} cap)
                </div>
              </td>

              <!-- Storage -->
              <td class="px-4 py-3">
                <template v-if="org.storage.limit_gb > 0">
                  <span class="text-sm" :class="storageUsageClass(org.storage)">
                    {{ org.storage.used_gb }} / {{ org.storage.limit_gb }} GB
                  </span>
                </template>
                <template v-else>
                  <span class="text-xs text-white/40">{{ org.storage.used_gb > 0 ? `${org.storage.used_gb} GB` : '—' }}</span>
                </template>
              </td>

              <!-- Webhooks -->
              <td class="px-4 py-3">
                <div class="flex flex-col gap-0.5">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border w-fit"
                    :class="webhookStatusBadge(org.webhook).cls"
                  >
                    {{ webhookStatusBadge(org.webhook).label }}
                  </span>
                  <span class="text-xs text-white/50">{{ formatRelativeTime(org.webhook.last_processed_at) }}</span>
                </div>
              </td>

              <!-- Flags -->
              <td class="px-4 py-3">
                <div v-if="Object.keys(org.health_flags).length" class="flex flex-wrap gap-1">
                  <span
                    v-for="(level, key) in org.health_flags"
                    :key="key"
                    class="inline-flex px-2 py-0.5 rounded text-xs border"
                    :class="level === 'critical' ? 'bg-red-600/20 text-red-400 border-red-500/30' : 'bg-amber-600/20 text-amber-400 border-amber-500/30'"
                  >
                    {{ flagLabels[key] ?? key }}
                  </span>
                </div>
                <span v-else class="text-xs text-white/40">—</span>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="org.subscriptions_url"
                    class="text-sm text-purple-400 hover:text-purple-300 transition"
                    title="View in Org Subscriptions"
                  >
                    Subscriptions
                  </a>
                  <a
                    v-if="org.has_stripe_id"
                    :href="org.tenant_billing_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-blue-400 hover:text-blue-300 transition"
                    title="Tenant billing page (customer must log in)"
                  >
                    Billing
                  </a>
                </div>
              </td>
            </tr>
            <tr v-if="!organizations.data.length">
              <td colspan="9" class="px-4 py-12 text-center text-white/40">
                No organizations match the current filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="organizations.last_page > 1" class="mt-4 flex items-center justify-between">
        <div class="text-sm text-white/50">
          Showing {{ (organizations.current_page - 1) * organizations.per_page + 1 }}–{{
            Math.min(organizations.current_page * organizations.per_page, organizations.total)
          }} of {{ organizations.total }}
        </div>
        <div class="flex gap-2">
          <template v-for="link in organizations.links" :key="link.label">
            <Link
              v-if="link.url"
              :href="link.url"
              class="px-3 py-1.5 rounded-lg text-sm border transition"
              :class="link.active ? 'bg-purple-600/20 border-purple-500/30 text-purple-400' : 'border-white/10 text-white/70 hover:bg-white/5'"
              v-html="link.label"
            />
            <span
              v-else
              class="px-3 py-1.5 rounded-lg text-sm text-white/40"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </Card>
  </PlatformLayout>
</template>

<style scoped>
.card-neo {
  @apply border-white/10;
}
</style>
