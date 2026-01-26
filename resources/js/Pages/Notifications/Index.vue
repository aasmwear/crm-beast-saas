<script setup lang="ts">
import { router } from '@inertiajs/vue3';

type NotificationItem = {
  id: string;
  read_at: string | null;
  created_at: string;
  data: Record<string, any>;
};

type Paginator<T> = {
  data: T[];
  links?: any[];
  meta?: any;
};

const props = defineProps<{
  notifications: Paginator<NotificationItem>;
  unreadCount?: number;
}>();

function markAllRead() {
  router.post(route('notifications.readAll', { organization: route().params.organization }));
}

function markRead(id: string) {
  router.post(route('notifications.read', { organization: route().params.organization, notification: id }));
}
</script>

<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-white">Notifications</h1>
        <p class="text-sm text-slate-400" v-if="typeof unreadCount === 'number'">
          Unread: {{ unreadCount }}
        </p>
      </div>

      <button
        class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-white text-sm"
        type="button"
        @click="markAllRead"
      >
        Mark all read
      </button>
    </div>

    <div class="bg-gray-900 rounded-xl overflow-hidden text-white">
      <ul class="divide-y divide-gray-800">
        <li
          v-for="n in props.notifications.data"
          :key="n.id"
          class="p-4 flex items-start justify-between gap-4"
        >
          <div class="min-w-0">
            <div class="font-semibold truncate">
              {{ n.data?.message || n.data?.summary || 'Notification' }}
            </div>
            <div class="text-sm opacity-70">
              {{ new Date(n.created_at).toLocaleString() }}
              <span v-if="n.read_at" class="ml-2 opacity-60">(read)</span>
              <span v-else class="ml-2 opacity-90">(unread)</span>
            </div>
          </div>

          <button
            v-if="!n.read_at"
            class="shrink-0 px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-white text-xs"
            type="button"
            @click="markRead(n.id)"
          >
            Mark read
          </button>
        </li>

        <li v-if="props.notifications.data.length === 0" class="p-6 text-slate-400">
          No notifications yet.
        </li>
      </ul>
    </div>
  </div>
</template>
