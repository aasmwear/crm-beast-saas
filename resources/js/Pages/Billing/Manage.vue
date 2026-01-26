<script setup>
const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? ''
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-semibold">Billing & Subscription</h1>

    <p class="text-gray-600">
      Manage your subscription, invoices, and payment methods. Clicking the button below will
      open the secure billing portal.
    </p>

    <form method="POST" action="/billing/portal" class="space-y-3">
      <input type="hidden" name="_token" :value="csrf" />
      <button class="px-3 py-2 rounded bg-indigo-600 text-white">
        Open Billing Portal
      </button>
    </form>
  </div>
</template>
