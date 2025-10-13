<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
defineProps<{ client: any }>();
</script>

<template>
  <Head :title="client.name" />
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl text-white">{{ client.name }}</h1>
      <Link :href="route('clients.index', route().params.organization)" class="underline">Back</Link>
    </div>
    <div class="rounded-2xl border border-[#3A3A42] bg-[#1A1B22] p-4 text-[#EDEEF6]">
      <div class="grid grid-cols-2 gap-4">
        <div><div class="text-[#A9ABB8]">Business</div><div>{{ client.business_name || '—' }}</div></div>
        <div><div class="text-[#A9ABB8]">Status</div><div class="capitalize">{{ client.project_activation_status || '—' }}</div></div>
        <div><div class="text-[#A9ABB8]">Email</div><div>{{ client.email || '—' }}</div></div>
        <div><div class="text-[#A9ABB8]">Phone</div><div>{{ client.phone_1 || '—' }}</div></div>
      </div>
    </div>
  </div>
</template>
