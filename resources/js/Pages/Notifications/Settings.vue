<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

type Prefs = {
  channels: {
    inapp: boolean;
    email: boolean;
  };
  types: Record<string, boolean>;
};

const props = defineProps<{ prefs: Prefs }>();

const form = useForm({
  prefs: props.prefs,
});

function save() {
  form.patch(route('notifications.settings.update', { organization: route().params.organization }));
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Notification Settings</h1>

      <button
        class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-white text-sm"
        type="button"
        :disabled="form.processing"
        @click="save"
      >
        Save
      </button>
    </div>

    <div class="bg-gray-900 rounded-2xl p-5 space-y-4 text-white">
      <p class="text-slate-400 text-sm">
        Controls are stored in <code class="text-slate-300">users.notification_prefs</code>.
      </p>

      <div class="space-y-3">
        <label class="flex items-center gap-3">
          <input type="checkbox" class="rounded" v-model="form.prefs.channels.inapp" />
          <span>In-app notifications</span>
        </label>

        <label class="flex items-center gap-3">
          <input type="checkbox" class="rounded" v-model="form.prefs.channels.email" />
          <span>Email notifications (later wiring)</span>
        </label>
      </div>

      <div v-if="form.errors.prefs" class="text-sm text-red-300">
        {{ form.errors.prefs }}
      </div>
    </div>
  </div>
</template>
