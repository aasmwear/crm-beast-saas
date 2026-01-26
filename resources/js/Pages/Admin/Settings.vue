<script setup lang="ts">
const props = defineProps<{ settings: Record<string, string|null> }>();
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">Admin Settings</h1>
    <form method="post" :action="route('admin.settings.save')" class="glass rounded-2xl p-4 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="space-y-1">
          <div class="text-sm text-slate-400">Slack Webhook</div>
          <input name="slack[webhook]" :value="props.settings['slack.webhook'] || ''" class="w-full rounded bg-white/5 border border-white/10 p-2" />
        </label>
        <label class="space-y-1">
          <div class="text-sm text-slate-400">Drive API Key</div>
          <input name="drive[api_key]" :value="props.settings['drive.api_key'] || ''" class="w-full rounded bg-white/5 border border-white/10 p-2" />
        </label>
        <label class="space-y-1">
          <div class="text-sm text-slate-400">SMTP From</div>
          <input name="smtp[from]" :value="props.settings['smtp.from'] || ''" class="w-full rounded bg-white/5 border border-white/10 p-2" />
        </label>
        <label class="space-y-1">
          <div class="text-sm text-slate-400">Zapier Webhook</div>
          <input name="zapier[webhook]" :value="props.settings['zapier.webhook'] || ''" class="w-full rounded bg-white/5 border border-white/10 p-2" />
        </label>
      </div>
      <button class="px-3 py-2 rounded-lg bg-[var(--primary)]">Save</button>
    </form>
  </div>
</template>
