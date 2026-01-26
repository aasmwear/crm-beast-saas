<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
const route = (window as any).route;
const page = usePage();
const hasStripe = page.props.hasStripe as boolean;
function goPortal(){ router.post(route('billing.portal')); }
</script>
<template>
  <div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">Billing</h1>
    <div v-if="!hasStripe" class="p-4 rounded bg-yellow-50 border border-yellow-200 mb-6">
      <p class="text-sm">
        Stripe is not configured. Ask an admin to set
        <code class="px-1 bg-black/5 rounded">CASHIER_KEY</code> and
        <code class="px-1 bg-black/5 rounded">CASHIER_SECRET</code> in the environment.
      </p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-xl border p-4">
        <h2 class="font-medium">Starter</h2>
        <p class="text-3xl font-bold my-2">$0</p>
        <ul class="text-sm list-disc ml-5">
          <li>Up to 3 clients</li><li>Basic boards</li>
        </ul>
      </div>
      <div class="rounded-xl border p-4">
        <h2 class="font-medium">Pro</h2>
        <p class="text-3xl font-bold my-2">$19</p>
        <ul class="text-sm list-disc ml-5">
          <li>Unlimited clients</li><li>Advanced boards</li><li>CSV export</li>
        </ul>
        <button class="mt-4 px-3 py-2 rounded bg-black text-white" @click="goPortal">Manage Subscription</button>
      </div>
    </div>
  </div>
</template>
