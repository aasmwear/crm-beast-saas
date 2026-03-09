<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps<{
  organizations: {
    data: Array<{
      id: number;
      name: string;
      slug: string;
      has_stripe_id: boolean;
      plan_key: string;
      status: string;
      seats_included: number;
      seat_limit: number | null;
      active_seats: number;
      addons_summary: Array<{ addon_key: string; mode: string; value_int: number | null; quantity: number }>;
      entitlements: { api_rpm: number | null; storage_gb: number | null; exports_per_day: number | null };
      trial_ends_at: string | null;
      current_period_ends_at: string | null;
    }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  filters: { search?: string; status?: string; plan?: string };
  planKeys: string[];
  statusOptions: string[];
}>();

const applyFilters = (updates: Partial<{ search: string; status: string; plan: string }>) => {
  const params: Record<string, string> = { ...props.filters, ...updates } as Record<string, string>;
  Object.keys(params).forEach((k) => (params[k] === '' || params[k] === undefined) && delete params[k]);
  router.get(route('platform.organizations.subscriptions'), params, { preserveState: true });
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
</script>

<template>
  <Head title="Org Subscriptions — Platform" />

  <PlatformLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white mb-2">Organization Subscriptions</h1>
      <p class="text-white/60">Canonical billing overview for all tenants (read-only)</p>
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
          <label class="block text-xs font-medium text-white/50 mb-1">Status</label>
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
          <label class="block text-xs font-medium text-white/50 mb-1">Plan</label>
          <select
            :value="filters.plan ?? ''"
            class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            @change="applyFilters({ plan: (($event.target as HTMLSelectElement)?.value || '') || undefined })"
          >
            <option value="">All</option>
            <option v-for="p in planKeys" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 hover:bg-purple-600/30 transition"
          @click="applyFilters({ search: undefined, status: undefined, plan: undefined })"
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
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Plan</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Stripe</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Seats</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Add-ons</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Entitlements</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            <tr
              v-for="org in organizations.data"
              :key="org.id"
              class="hover:bg-white/5 transition"
            >
              <td class="px-4 py-3">
                <div>
                  <div class="text-sm font-medium text-white">{{ org.name }}</div>
                  <div class="text-xs text-white/50 font-mono">{{ org.slug }}</div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-white/80">{{ org.plan_key }}</span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                  :class="statusBadgeClass(org.status)"
                >
                  {{ org.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span v-if="org.has_stripe_id" class="text-xs text-green-400">Linked</span>
                <span v-else class="text-xs text-white/40">—</span>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-white/80">{{ org.active_seats }} / {{ org.seats_included }}</span>
                <span v-if="org.seat_limit != null" class="text-xs text-white/50"> (limit {{ org.seat_limit }})</span>
              </td>
              <td class="px-4 py-3">
                <div v-if="org.addons_summary.length" class="flex flex-wrap gap-1">
                  <span
                    v-for="a in org.addons_summary"
                    :key="a.addon_key"
                    class="inline-flex px-2 py-0.5 rounded text-xs bg-white/10 text-white/70"
                    :title="`${a.addon_key} (${a.mode})`"
                  >
                    {{ a.addon_key }}: {{ a.value_int ?? a.quantity }}
                  </span>
                </div>
                <span v-else class="text-xs text-white/40">—</span>
              </td>
              <td class="px-4 py-3">
                <div class="text-xs text-white/60 space-y-0.5">
                  <div v-if="org.entitlements.api_rpm != null">api: {{ org.entitlements.api_rpm }}/m</div>
                  <div v-if="org.entitlements.storage_gb != null">storage: {{ org.entitlements.storage_gb }} GB</div>
                  <div v-if="org.entitlements.exports_per_day != null">exports: {{ org.entitlements.exports_per_day }}/d</div>
                  <span v-if="org.entitlements.api_rpm == null && org.entitlements.storage_gb == null && org.entitlements.exports_per_day == null">—</span>
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="route('platform.organizations.show', { organization: org.slug })"
                  class="text-sm text-purple-400 hover:text-purple-300 transition"
                >
                  View →
                </Link>
              </td>
            </tr>
            <tr v-if="!organizations.data.length">
              <td colspan="8" class="px-4 py-12 text-center text-white/40">
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
