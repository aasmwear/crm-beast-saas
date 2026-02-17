<template>
  <div class="min-h-screen white-bg text-white">
    <CommandPalette />
    <Toast />
    <IconRail
      :mobile-open="mobileMenuOpen"
      @close="mobileMenuOpen = false"
    />

    <div class=".white-bg">
    <div class="bg-background bordermainradius">
      <div class="bg-background-anima">
                <div class="bg-orb-A"></div>
                <div class="bg-orb-B"></div>
                <div class="bg-orb-C"></div>
              </div>
        
        <header class="top-0 z-30">
        <div class="mx-auto h-14 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
          <!-- Mobile hamburger: visible on screens smaller than lg -->
          <button
            type="button"
            class="lg:hidden p-2 -ml-2 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition"
            aria-label="Open menu"
            @click="mobileMenuOpen = true"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <div class="text-sm text-white/60 flex items-center gap-1">
            <template v-for="(b, index) in breadcrumbs" :key="index">
              <Link v-if="b.url !== '#'" :href="b.url" class="text-white/80 hover:text-white/60 transition">
                {{ b.label }}
              </Link>
              <span v-else class="text-white/70 font-semibold">
                {{ b.label }}
              </span>
              
              <span 
                v-if="index < breadcrumbs.length - 1" 
                class="text-white/40 mx-1"
              >
                /
              </span>
            </template>
          </div>

          <div class="topbar">
          <div class="search-pill">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <input type="text" placeholder="Search" />
          </div>

          <NotificationDropdown />
          </div>

          </div>
      </header>

      <main class="mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <slot />
      </main>
    </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import IconRail from '@/Components/ui/IconRail.vue'
import Toast from '@/Components/ui/Toast.vue'
import CommandPalette from '@/Components/Global/CommandPalette.vue'
import NotificationDropdown from '@/Components/Global/NotificationDropdown.vue'
// NOTE: The `applyFireflyStyles` function is assumed to be defined here or imported.

// DO NOT REMOVE any existing code in the <script setup> block below this.

/* Ziggy wrapper */
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  // @ts-ignore
  (window as any).route(name, params, absolute, config)

/* Resolve org slug safely */
const org = computed(() => {
  // @ts-ignore
  const p = (window as any).route()?.params ?? {}
  if (p.organization) return p.organization
  const parts = window.location.pathname.split('/').filter(Boolean)
  const i = parts.indexOf('org')
  return i >= 0 && parts[i + 1] ? parts[i + 1] : 'acme'
})

const mobileMenuOpen = ref(false)

/* User initials for avatar */
const user = computed<any>(() => (usePage().props as any)?.auth?.user ?? {})
const initials = computed(() => {
  const n = String(user.value?.name ?? 'U').trim()
  return n.split(/\s+/).map(s => s[0]).join('').slice(0, 2).toUpperCase()
})

/* Log out helper (Breeze-style) */
const logout = () => router.post('/logout')

// --- CLEANED BREADCRUMB LOGIC ---
const breadcrumbs = computed(() => {
  const componentPath = usePage().component as string; // e.g., 'Clients/Show'
  const pathParts = componentPath.split('/');        // e.g., ['Clients', 'Show']
  const moduleName = pathParts[0] || 'Dashboard';
  const pageName = pathParts[1] ? pathParts[1].replace('.vue', '') : 'Index';
  const orgSlug = org.value;
  
  let routePath = [];
  
  // 1. Dashboard is the top level if not in a module directory
  if (moduleName === 'Dashboard') {
    routePath.push({ label: 'Dashboard', url: r('dashboard', { organization: orgSlug }) });
    routePath.push({ label: 'Overview', url: '#' });

  } else {
    // 2. Main Module Link (e.g., 'Clients')
    const indexRouteName = `${moduleName.toLowerCase()}.index`; // e.g., 'clients.index'
    routePath.push({ label: moduleName, url: r(indexRouteName, { organization: orgSlug }) });

    // 3. Current Page Label (based on file name: Index, Create, Show, Edit, etc.)
    const nameMap: { [key: string]: string } = {
      'Index': 'List',
      'Create': 'New',
      'Show': 'Details',
      'Edit': 'Edit',
      'Pipeline': 'Pipeline',
      'Import': 'Import',
      'QuickCreate': 'Quick Create',
    };
    
    // Add the specific page name. If it's the Index page, the module link covers it, 
    // but we add 'List' for clarity.
    if (pageName !== 'Index') {
      // Use mapped name or the page name itself
      const label = nameMap[pageName] || pageName;
      routePath.push({ label: label, url: '#' });
    } else {
        // Only show 'List' if it's not the only item (i.e., we are on a module index)
        routePath.push({ label: 'List', url: '#' });
    }
  } 

  return routePath;
});
// ---------------------------------

// Firefly styles hook (assuming this was in the original component)
// @ts-ignore
onMounted(() => { if (typeof applyFireflyStyles === 'function') applyFireflyStyles(); });
</script>
