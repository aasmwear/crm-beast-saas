<script setup lang="ts">
import { route } from '@ziggy'
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'

type Tenant = { id:number; name:string; slug:string } | null
const page = usePage<{ tenant: Tenant }>()
const tenant = computed(() => page.props.tenant)
</script>

<template>
  <div class="min-h-screen bg-slate-900 text-slate-200">
    <nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <!-- Brand -->
          <div class="flex items-center gap-3">
            <Link :href="route('tenant.dashboard', { org: tenant?.slug })" class="text-slate-100 font-semibold">
              CRM Beast
            </Link>

            <!-- Top tabs -->
            <div class="hidden md:flex items-center gap-3 ml-6">
              <NavLink
                :href="route('tenant.dashboard', { org: tenant?.slug })"
                :active="route().current('dashboard')"
              >
                Overview
              </NavLink>

              <NavLink
                :href="route('projects.board', { org: tenant?.slug })"
                :active="route().current('projects.board')"
              >
                Pipeline
              </NavLink>

              <!-- Placeholder tabs (we’ll wire later) -->
              <NavLink :href="'#'" :active="false">Teams</NavLink>
              <Link :href="route('clients.index', { organization: route().params.organization })" class="...">Clients</Link>
              <NavLink :href="'#'" :active="false">Reports</NavLink>
            </div>
          </div>

          <!-- Right side (tenant chip) -->
          <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full bg-white/10 text-xs">
              {{ tenant?.name ?? '—' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Mobile -->
      <div class="md:hidden border-t border-white/10">
        <div class="px-2 py-3 space-y-1">
          <ResponsiveNavLink
            :href="route('tenant.dashboard', { org: tenant?.slug })"
            :active="route().current('dashboard')"
          >Overview</ResponsiveNavLink>

          <ResponsiveNavLink
            :href="route('projects.board', { org: tenant?.slug })"
            :active="route().current('projects.board')"
          >Pipeline</ResponsiveNavLink>

          <ResponsiveNavLink href="#">Teams</ResponsiveNavLink>
          <ResponsiveNavLink href="#">Clients</ResponsiveNavLink>
          <ResponsiveNavLink href="#">Reports</ResponsiveNavLink>
        </div>
      </div>
    </nav>

    <main>
      <slot />
    </main>
  </div>
</template>
