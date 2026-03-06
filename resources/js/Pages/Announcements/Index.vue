<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

interface Announcement {
  id: number
  title: string
  body: string | null
  pinned: boolean
  created_at: string | null
}

const props = defineProps<{
  organization: {
    id: number
    name: string
    slug: string
  }
  announcements: {
    data: Announcement[]
  }
}>()

// Ziggy route helper wrapper
const routeGlobal = (window as any).route || ((name: string) => name)
const r = (
  name: string,
  params: Record<string, unknown> = {},
  absolute = false,
  config?: any,
) => routeGlobal(name, params, absolute, config)

const orgSlug = computed(() => props.organization.slug)

const form = useForm({
  title: '',
  body: '',
  pinned: false,
})

const showModal = ref(false)
const editingId = ref<number | null>(null)

const isEditing = computed(() => editingId.value !== null)

function openCreate() {
  editingId.value = null
  form.reset('title', 'body', 'pinned')
  form.clearErrors()
  form.pinned = false
  showModal.value = true
}

function openEdit(a: Announcement) {
  editingId.value = a.id
  form.title = a.title
  form.body = a.body ?? ''
  form.pinned = a.pinned
  form.clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function submit() {
  if (isEditing.value && editingId.value !== null) {
    form.put(
      r('announcements.update', {
        organization: orgSlug.value,
        announcement: editingId.value,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          showModal.value = false
        },
      },
    )
  } else {
    form.post(
      r('announcements.store', {
        organization: orgSlug.value,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          showModal.value = false
          form.reset('title', 'body', 'pinned')
        },
      },
    )
  }
}

function togglePin(a: Announcement) {
  router.put(
    r('announcements.update', {
      organization: orgSlug.value,
      announcement: a.id,
    }),
    {
      title: a.title,
      body: a.body,
      pinned: !a.pinned,
    },
    {
      preserveScroll: true,
    },
  )
}

function destroyAnnouncement(a: Announcement) {
  if (!confirm('Delete this announcement?')) return

  router.delete(
    r('announcements.destroy', {
      organization: orgSlug.value,
      announcement: a.id,
    }),
    {
      preserveScroll: true,
    },
  )
}

function formatDate(value: string | null) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <Head title="Announcements" />

  <PageShell
    :header="{
      breadcrumb: `Organization • ${String(orgSlug).toUpperCase()}`,
      title: 'Announcements',
      subtitle: `Share important updates with everyone in ${organization.name}.`,
    }"
  >
    <template #header-actions>
      <button
        type="button"
        class="inline-flex items-center rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/10"
        @click="openCreate"
      >
        <span class="mr-1.5">+</span>
        <span>New announcement</span>
      </button>
    </template>

    <!-- List -->
    <div class="rounded-2xl border border-white/10 bg-black/30 backdrop-blur-sm">
      <div
        v-if="!announcements.data.length"
        class="p-6 text-sm text-white/60"
      >
        No announcements yet. Create the first one for your team.
      </div>

      <ul
        v-else
        class="divide-y divide-white/5"
      >
        <li
          v-for="a in announcements.data"
          :key="a.id"
          class="flex items-start justify-between gap-4 p-4"
        >
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h2 class="truncate text-sm font-semibold text-white">
                {{ a.title }}
              </h2>

              <span
                v-if="a.pinned"
                class="inline-flex items-center rounded-full border border-amber-400/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-300"
              >
                Pinned
              </span>
            </div>

            <p class="mt-1 whitespace-pre-line text-sm text-white/70">
              {{ a.body }}
            </p>

            <p class="mt-2 text-xs text-white/40">
              {{ formatDate(a.created_at) }}
            </p>
          </div>

          <div class="flex flex-col items-end gap-1 text-xs">
            <button
              class="text-white/70 hover:text-white"
              @click="openEdit(a)"
            >
              Edit
            </button>
            <button
              class="text-amber-300 hover:text-amber-200"
              @click="togglePin(a)"
            >
              {{ a.pinned ? 'Unpin' : 'Pin' }}
            </button>
            <button
              class="text-red-400 hover:text-red-300"
              @click="destroyAnnouncement(a)"
            >
              Delete
            </button>
          </div>
        </li>
      </ul>
    </div>

    <!-- Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#12141a] p-6 shadow-xl">
        <h2 class="text-sm font-semibold text-white">
          {{ isEditing ? 'Edit announcement' : 'New announcement' }}
        </h2>

        <form
          class="mt-4 space-y-4"
          @submit.prevent="submit"
        >
          <div>
            <label class="text-xs font-medium text-white/70">
              Title
            </label>
            <input
              v-model="form.title"
              type="text"
              class="mt-1 w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-primary focus:ring-1 focus:ring-primary"
              :class="{ 'border-red-500/70': form.errors.title }"
            >
            <p
              v-if="form.errors.title"
              class="mt-1 text-xs text-red-400"
            >
              {{ form.errors.title }}
            </p>
          </div>

          <div>
            <label class="text-xs font-medium text-white/70">
              Message
            </label>
            <textarea
              v-model="form.body"
              rows="4"
              class="mt-1 w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-primary focus:ring-1 focus:ring-primary"
              :class="{ 'border-red-500/70': form.errors.body }"
            />
            <p
              v-if="form.errors.body"
              class="mt-1 text-xs text-red-400"
            >
              {{ form.errors.body }}
            </p>
          </div>

          <label class="inline-flex items-center gap-2 text-xs text-white/70">
            <input
              v-model="form.pinned"
              type="checkbox"
              class="h-3 w-3 rounded border-white/20 bg-black/40"
            >
            <span>Pin at top</span>
          </label>

          <div class="mt-4 flex items-center justify-end gap-2">
            <button
              type="button"
              class="rounded-xl border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 hover:bg-white/10"
              @click="closeModal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="rounded-xl bg-primary px-4 py-1.5 text-xs font-medium text-black hover:bg-primary/90 disabled:opacity-60"
              :disabled="form.processing"
            >
              {{ isEditing ? 'Save changes' : 'Post announcement' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </PageShell>
</template>
