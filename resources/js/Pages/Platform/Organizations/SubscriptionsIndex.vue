<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
      addons_summary: Array<{ id: number; addon_key: string; mode: string; value_int: number | null; quantity: number; active: boolean }>;
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
  addonKeys: string[];
}>();

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string; info?: string } }).flash);

const overrideModalOrg = ref<typeof props.organizations.data[0] | null>(null);

const subForm = ref({ plan_key: '', seat_limit: '' as string | number, clear_seat_limit: false });
const addonForm = ref({ addon_key: 'storage_gb', mode: 'augment' as 'augment' | 'set', quantity: 1, value_int: null as number | null, active: true });
const editingAddonId = ref<number | null>(null);
const editAddonForm = ref({ mode: 'augment' as 'augment' | 'set', value_int: null as number | null, active: true });

function openOverride(org: typeof props.organizations.data[0]) {
  overrideModalOrg.value = org;
  subForm.value = { plan_key: org.plan_key, seat_limit: org.seat_limit ?? '', clear_seat_limit: false };
  addonForm.value = { addon_key: 'storage_gb', mode: 'augment', quantity: 1, value_int: null, active: true };
  editingAddonId.value = null;
}

function closeOverride() {
  overrideModalOrg.value = null;
}

watch(overrideModalOrg, (org) => {
  if (org) {
    subForm.value = { plan_key: org.plan_key, seat_limit: org.seat_limit ?? '', clear_seat_limit: false };
  }
});

function saveSubscription() {
  if (!overrideModalOrg.value) return;
  const payload: Record<string, string | number> = { plan_key: subForm.value.plan_key };
  if (subForm.value.clear_seat_limit) payload.clear_seat_limit = '1';
  else if (subForm.value.seat_limit !== '') payload.seat_limit = Number(subForm.value.seat_limit);
  router.patch(
    route('platform.organizations.subscription.update', { organization: overrideModalOrg.value.slug }),
    payload,
    { preserveScroll: true, onSuccess: () => closeOverride() }
  );
}

function createAddon() {
  if (!overrideModalOrg.value) return;
  router.post(
    route('platform.organizations.addons.store', { organization: overrideModalOrg.value.slug }),
    {
      addon_key: addonForm.value.addon_key,
      mode: addonForm.value.mode,
      quantity: addonForm.value.quantity,
      value_int: addonForm.value.value_int,
      active: addonForm.value.active,
    },
    { preserveScroll: true }
  );
  addonForm.value = { addon_key: 'storage_gb', mode: 'augment', quantity: 1, value_int: null, active: true };
}

function startEditAddon(a: { id: number; mode: string; value_int: number | null; active: boolean }) {
  editingAddonId.value = a.id;
  editAddonForm.value = { mode: a.mode as 'augment' | 'set', value_int: a.value_int, active: a.active };
}

function cancelEditAddon() {
  editingAddonId.value = null;
}

function saveAddon() {
  if (!overrideModalOrg.value || editingAddonId.value == null) return;
  router.patch(
    route('platform.organizations.addons.update', { organization: overrideModalOrg.value.slug, addon: editingAddonId.value }),
    {
      mode: editAddonForm.value.mode,
      value_int: editAddonForm.value.value_int,
      active: editAddonForm.value.active,
    },
    { preserveScroll: true, onSuccess: () => { editingAddonId.value = null; } }
  );
}

function deactivateAddon(addonId: number) {
  if (!overrideModalOrg.value) return;
  if (!confirm('Deactivate this add-on? It will no longer apply to entitlements.')) return;
  router.delete(
    route('platform.organizations.addons.destroy', { organization: overrideModalOrg.value.slug, addon: addonId }),
    { preserveScroll: true }
  );
}

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
    <!-- Flash -->
    <div v-if="flash?.success" class="mb-4 px-4 py-2 rounded-lg bg-green-600/20 border border-green-500/30 text-green-400 text-sm">
      {{ flash.success }}
    </div>
    <div v-if="flash?.error" class="mb-4 px-4 py-2 rounded-lg bg-red-600/20 border border-red-500/30 text-red-400 text-sm">
      {{ flash.error }}
    </div>

    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white mb-2">Organization Subscriptions</h1>
      <p class="text-white/60">Canonical billing overview + platform overrides (does not mutate Stripe)</p>
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
                    v-for="a in org.addons_summary.filter((x) => x.active)"
                    :key="a.id"
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
              <td class="px-4 py-3 text-right space-x-2">
                <button
                  type="button"
                  class="text-sm text-amber-400 hover:text-amber-300 transition"
                  @click="openOverride(org)"
                >
                  Override
                </button>
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

    <!-- Override Modal -->
    <Teleport to="body">
      <div
        v-if="overrideModalOrg"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
        @click.self="closeOverride"
      >
        <div class="bg-slate-900 border border-white/10 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto m-4">
          <div class="p-6 border-b border-white/10">
            <h2 class="text-lg font-bold text-white">Override: {{ overrideModalOrg.name }}</h2>
            <p class="text-xs text-white/50 mt-1">Internal operator control — does not mutate Stripe</p>
          </div>
          <div class="p-6 space-y-6">
            <!-- Subscription -->
            <div>
              <h3 class="text-sm font-medium text-white/70 mb-3">Subscription</h3>
              <div class="space-y-3">
                <div>
                  <label class="block text-xs text-white/50 mb-1">Plan</label>
                  <select
                    v-model="subForm.plan_key"
                    class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white"
                  >
                    <option v-for="p in planKeys" :key="p" :value="p">{{ p }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs text-white/50 mb-1">Seat limit override</label>
                  <input
                    v-model.number="subForm.seat_limit"
                    type="number"
                    min="1"
                    placeholder="Plan default"
                    class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white placeholder-white/40"
                  />
                </div>
                <label class="flex items-center gap-2 text-sm text-white/70">
                  <input v-model="subForm.clear_seat_limit" type="checkbox" class="rounded" />
                  Clear seat limit override
                </label>
                <button
                  type="button"
                  class="px-4 py-2 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 hover:bg-purple-600/30"
                  @click="saveSubscription"
                >
                  Save subscription
                </button>
              </div>
            </div>

            <!-- Add-ons -->
            <div>
              <h3 class="text-sm font-medium text-white/70 mb-3">Add-ons</h3>
              <div class="space-y-2 mb-4">
                <div v-for="a in overrideModalOrg.addons_summary" :key="a.id" class="flex items-center justify-between py-2 border-b border-white/5">
                  <span class="text-sm text-white/80 font-mono">{{ a.addon_key }} ({{ a.mode }})</span>
                  <span class="text-xs" :class="a.active ? 'text-green-400' : 'text-white/40'">{{ a.active ? 'Active' : 'Inactive' }}</span>
                  <div class="flex gap-2">
                    <template v-if="editingAddonId === a.id">
                      <input v-model.number="editAddonForm.value_int" type="number" min="0" placeholder="Value" class="w-20 px-2 py-1 rounded text-sm bg-white/5 border border-white/10 text-white" />
                      <select v-model="editAddonForm.mode" class="px-2 py-1 rounded text-sm bg-white/5 border border-white/10 text-white">
                        <option value="augment">augment</option>
                        <option value="set">set</option>
                      </select>
                      <label class="flex items-center gap-1 text-xs"><input v-model="editAddonForm.active" type="checkbox" class="rounded" />Active</label>
                      <button type="button" class="text-xs text-green-400" @click="saveAddon">Save</button>
                      <button type="button" class="text-xs text-white/50" @click="cancelEditAddon">Cancel</button>
                    </template>
                    <template v-else>
                      <button v-if="a.active" type="button" class="text-xs text-amber-400" @click="startEditAddon(a)">Edit</button>
                      <button v-if="a.active" type="button" class="text-xs text-red-400" @click="deactivateAddon(a.id)">Deactivate</button>
                      <button v-if="!a.active" type="button" class="text-xs text-amber-400" @click="startEditAddon(a)">Reactivate</button>
                    </template>
                  </div>
                </div>
              </div>
              <div class="flex flex-wrap gap-2 items-end">
                <select v-model="addonForm.addon_key" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
                  <option v-for="k in addonKeys" :key="k" :value="k">{{ k }}</option>
                </select>
                <select v-model="addonForm.mode" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
                  <option value="augment">augment</option>
                  <option value="set">set</option>
                </select>
                <input v-model.number="addonForm.value_int" type="number" min="0" placeholder="Value" class="w-24 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white" />
                <button
                  type="button"
                  class="px-4 py-2 rounded-lg bg-green-600/20 border border-green-500/30 text-green-400 hover:bg-green-600/30"
                  @click="createAddon"
                >
                  Add
                </button>
              </div>
            </div>
          </div>
          <div class="p-6 border-t border-white/10">
            <button type="button" class="text-sm text-white/50 hover:text-white" @click="closeOverride">Close</button>
          </div>
        </div>
      </div>
    </Teleport>
  </PlatformLayout>
</template>

<style scoped>
.card-neo {
  @apply border-white/10;
}
</style>
