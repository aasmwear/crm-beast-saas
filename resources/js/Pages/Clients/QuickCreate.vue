<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

// NOTE: This component is NOT using AuthenticatedLayout, 
// as it is likely intended for use within a modal or sidebar in another page.

// --- SAFE ROUTE LOGIC ---
const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'
// -----------------------

const page = usePage()
const org = computed(() =>
  (page.props as any).tenant?.slug ?? (page.props as any).organization?.slug ?? 'acme'
)

// Get CSRF token for plain POST requests
const csrf =
  (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ||
  ''

const name = ref('')
const niche = ref('')
const status = ref('active')

// UI Helpers (Matching Dark Dashboard Theme)
const inputClass = 'mt-1 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-white placeholder-white/50 focus:border-[var(--primary)] focus:ring-1 focus:ring-[var(--primary)] transition'
const labelClass = 'block text-sm text-white/60'
</script>

<template>
  <form
    method="post"
    :action="r('clients.store', { organization: org })"
    class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md space-y-4"
  >
    <h3 class="text-white text-lg font-medium">Quick Client Add</h3>
    <input type="hidden" name="_token" :value="csrf" />
    <div class="grid gap-3 md:grid-cols-3">
      <label class="block">
        <span :class="labelClass">Company</span>
        <input
          v-model="name"
          :class="inputClass"
          name="company_name"
          placeholder="Acme LLC"
          required
        />
      </label>

      <label class="block">
        <span :class="labelClass">Niche</span>
        <input
          v-model="niche"
          :class="inputClass"
          name="niche"
          placeholder="SaaS / Marketing"
        />
      </label>

      <label class="block">
        <span :class="labelClass">Status</span>
        <select
          v-model="status"
          :class="inputClass"
          name="status"
        >
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="lead">Lead</option>
          <option value="paused">Paused</option>
        </select>
      </label>
    </div>
    
    <div class="flex justify-end pt-2">
        <button type="submit" class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90">
            Create Client
        </button>
    </div>
  </form>
</template>
