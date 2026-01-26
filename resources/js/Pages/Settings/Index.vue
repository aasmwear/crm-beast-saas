<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
const page = usePage()
const org = (page.props as any).tenant?.slug ?? (page.props as any).organization?.slug ?? 'acme'
const form = useForm({ slack_webhook_url:'', smtp_host:'', smtp_user:'', smtp_pass:'', google_drive_key:'' })
function save(){ form.post(route('settings.save', { organization: org })) }
</script>
<template>
  <div class="p-6"><div class="bg-gray-900 rounded-xl text-white p-4 space-y-3 max-w-2xl">
    <div class="font-semibold">Integrations</div>
    <input v-model="form.slack_webhook_url" placeholder="Slack webhook URL" class="px-3 py-2 rounded bg-gray-800 w-full" />
    <input v-model="form.google_drive_key" placeholder="Google Drive Key" class="px-3 py-2 rounded bg-gray-800 w-full" />
    <div class="font-semibold pt-4">Email (SMTP)</div>
    <input v-model="form.smtp_host" placeholder="SMTP Host" class="px-3 py-2 rounded bg-gray-800 w-full" />
    <input v-model="form.smtp_user" placeholder="SMTP User" class="px-3 py-2 rounded bg-gray-800 w-full" />
    <input v-model="form.smtp_pass" type="password" placeholder="SMTP Pass" class="px-3 py-2 rounded bg-gray-800 w-full" />
    <button @click="save" class="px-3 py-2 rounded bg-indigo-600">Save</button>
  </div></div>
</template>
