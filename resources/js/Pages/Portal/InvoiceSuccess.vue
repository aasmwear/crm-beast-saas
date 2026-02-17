<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineOptions({ layout: PortalLayout })

defineProps<{
  invoice: {
    id: number
    number: string
    status: string
    paid_at: string | null
  }
}>()

const r = (name: string) => (window as any).route?.(name) ?? '/portal/dashboard'
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="rounded-full bg-emerald-500/20 p-4 mb-6">
      <svg class="h-12 w-12 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>
    <h1 class="text-2xl font-semibold text-white">
      Payment Successful
    </h1>
    <p class="mt-2 text-white/70">
      Thank you for your payment. Invoice {{ invoice.number }} has been paid.
    </p>
    <Link
      :href="r('portal.dashboard')"
      class="mt-8 inline-flex items-center rounded-full bg-indigo-500 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-400"
    >
      Return to Dashboard
    </Link>
  </div>
</template>
