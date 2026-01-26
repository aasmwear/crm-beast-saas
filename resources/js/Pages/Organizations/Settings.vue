<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';

// 1. Define types for props used in this component
interface OrganizationProps {
  slug: string;
  name: string;
}
interface FlashProps {
    success?: string;
}
interface CustomPageProps {
    organization: OrganizationProps;
    flash?: FlashProps;
    [key: string]: any;
}

// 2. Apply types to usePage() and declare route()
const page = usePage<CustomPageProps>();
const org = page.props.organization; // 'org' is now correctly typed
const route = (window as any).route; // Fix: Cannot find name 'route'

const form = useForm({ name: org.name as string, slug: org.slug as string });

function submit(){ form.patch(route('org.settings.update', { organization: org.slug })); }
</script>
<template>
  <div class="max-w-xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">Organization Settings</h1>
    <form @submit.prevent="submit" class="space-y-4">
      <div><label class="block text-sm mb-1">Name</label><input v-model="form.name" class="w-full rounded border px-3 py-2" /></div>
      <div><label class="block text-sm mb-1">Slug</label><input v-model="form.slug" class="w-full rounded border px-3 py-2" /></div>
      <button class="px-3 py-2 rounded bg-black text-white" :disabled="form.processing">Save</button>
    </form>
    <p v-if="page.props.flash?.success" class="mt-4 text-green-700">{{ page.props.flash.success }}</p>
  </div>
</template>