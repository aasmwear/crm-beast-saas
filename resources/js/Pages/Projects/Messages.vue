<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface Attachment {
  path: string
  name?: string
  size?: number
  mime?: string
}

interface MessageUser {
  id: number | null
  name: string | null
}

interface Reply {
  id: number
  body: string | null
  created_at: string | null
  user: MessageUser | null
  attachments?: Attachment[] | null
}

interface Thread {
  id: number
  body: string | null
  created_at: string | null
  user: MessageUser | null
  attachments?: Attachment[] | null
  replies?: Reply[] | null
}

const props = defineProps<{
  organization: { id: number; name: string; slug: string }
  project: { id: number; title: string }
  threads: Thread[]
  canPost: boolean
}>()

const page = usePage()

const orgSlug = computed(() => {
  const p = page.props as any
  return props.organization?.slug ?? p?.tenant?.slug ?? 'acme'
})

const form = useForm<{
  body: string
  parent_id: number | null
  attachments: File[] | null
}>({
  body: '',
  parent_id: null,
  attachments: null,
})

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files
  if (!files) {
    form.attachments = null
    return
  }
  form.attachments = Array.from(files)
}

function submit() {
  if (!props.canPost) return

  form.post(
    route('projects.messages.store', {
      organization: orgSlug.value,
      project: props.project.id,
    }),
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.reset('body', 'attachments', 'parent_id')
      },
    },
  )
}

function formatDateTime(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function initials(name?: string | null): string {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
  return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div
      class="mb-2 rounded-2xl bg-gradient-to-br from-[rgba(13,15,18,0.9)] via-[rgba(18,18,40,0.85)] to-transparent border border-white/5 px-5 py-4"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-lg font-semibold tracking-tight text-white">
            Project Messages
          </h1>
          <p class="mt-1 text-sm text-white/60">
            Threaded discussion and file attachments for
            <span class="font-semibold text-white">{{ project.title }}</span>.
          </p>
        </div>

        <div class="inline-flex items-center gap-1 rounded-full bg-white/5 p-1 text-xs">
          <Link
            :href="route('projects.index', { organization: orgSlug })"
            class="rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10"
          >
            Back to Projects
          </Link>
        </div>
      </div>
    </div>

    <!-- Thread List -->
    <div class="glass rounded-2xl border border-white/5 bg-slate-950/60 p-4">
      <div class="mb-3 text-xs text-white/60">
        {{ threads.length }} message<span v-if="threads.length !== 1">s</span> in this project
      </div>

      <div
        v-if="!threads.length"
        class="rounded-xl border border-dashed border-white/10 bg-slate-950/70 p-6 text-center text-sm text-white/40"
      >
        No messages yet. Start the conversation below.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="thread in threads"
          :key="thread.id"
          class="rounded-xl border border-white/10 bg-slate-950/80 p-4"
        >
          <!-- Root message -->
          <div class="flex items-start gap-3">
            <div
              class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--primary)]/20 text-[11px] font-semibold text-[var(--primary)]"
            >
              {{ initials(thread.user?.name ?? '') }}
            </div>

            <div class="flex-1">
              <div class="flex items-center justify-between gap-2">
                <div>
                  <div class="text-sm font-semibold text-white">
                    {{ thread.user?.name ?? 'Unknown user' }}
                  </div>
                  <div class="text-[11px] text-white/50">
                    {{ formatDateTime(thread.created_at) }}
                  </div>
                </div>
              </div>

              <div v-if="thread.body" class="mt-3 whitespace-pre-line text-sm text-white/80">
                {{ thread.body }}
              </div>

              <div v-if="thread.attachments?.length" class="mt-3 text-xs text-white/80">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-white/50">
                  Attachments
                </div>
                <ul class="mt-1 space-y-1">
                  <li
                    v-for="(att, idx) in thread.attachments"
                    :key="idx"
                  >
                    <a
                      :href="
                        route('projects.messages.download', {
                          organization: orgSlug,
                          project: project.id,
                          message: thread.id,
                          index: idx,
                        })
                      "
                      class="underline underline-offset-2 hover:text-[var(--primary)]"
                    >
                      {{ att.name || att.path }}
                    </a>
                  </li>
                </ul>
              </div>

              <!-- Replies (one level) -->
              <div
                v-if="thread.replies && thread.replies.length"
                class="mt-4 space-y-3 border-l border-white/10 pl-4"
              >
                <div
                  v-for="reply in thread.replies"
                  :key="reply.id"
                  class="flex items-start gap-3"
                >
                  <div
                    class="mt-1 flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-[10px] font-semibold text-white/80"
                  >
                    {{ initials(reply.user?.name ?? '') }}
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center justify-between gap-2">
                      <div>
                        <div class="text-xs font-semibold text-white">
                          {{ reply.user?.name ?? 'Unknown user' }}
                        </div>
                        <div class="text-[10px] text-white/50">
                          {{ formatDateTime(reply.created_at) }}
                        </div>
                      </div>
                    </div>

                    <div v-if="reply.body" class="mt-2 whitespace-pre-line text-xs text-white/80">
                      {{ reply.body }}
                    </div>

                    <div v-if="reply.attachments?.length" class="mt-2 text-[11px] text-white/80">
                      <div class="text-[10px] font-semibold uppercase tracking-wide text-white/50">
                        Attachments
                      </div>
                      <ul class="mt-1 space-y-1">
                        <li
                          v-for="(att, idx) in reply.attachments"
                          :key="idx"
                        >
                          <a
                            :href="
                              route('projects.messages.download', {
                                organization: orgSlug,
                                project: project.id,
                                message: reply.id,
                                index: idx,
                              })
                            "
                            class="underline underline-offset-2 hover:text-[var(--primary)]"
                          >
                            {{ att.name || att.path }}
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Composer -->
    <div
      v-if="canPost"
      class="glass rounded-2xl border border-white/5 bg-slate-950/70 p-4"
    >
      <form @submit.prevent="submit" enctype="multipart/form-data" class="space-y-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-white/60">
          New Message
        </div>

        <textarea
          v-model="form.body"
          rows="3"
          class="w-full rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-[var(--primary)] focus:outline-none"
          placeholder="Share an update, ask a question, or drop details for the team…"
        />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3 text-xs text-white/70">
            <label
              class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-white/15 bg-slate-950/80 px-3 py-1 hover:bg-slate-900"
            >
              <span class="text-[11px] uppercase tracking-wide">Add files</span>
              <input
                type="file"
                multiple
                class="hidden"
                @change="onFileChange"
              />
            </label>
            <div v-if="form.attachments && form.attachments.length" class="text-[11px] text-white/50">
              {{ form.attachments.length }} file<span v-if="form.attachments.length !== 1">s</span> selected
            </div>
          </div>

          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-full bg-[var(--primary)] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-white shadow-[0_0_18px_rgba(139,124,255,0.75)] disabled:opacity-50"
            :disabled="form.processing || (!form.body && (!form.attachments || !form.attachments.length))"
          >
            {{ form.processing ? 'Posting…' : 'Post Message' }}
          </button>
        </div>
      </form>
    </div>

    <div
      v-else
      class="rounded-2xl border border-dashed border-white/10 bg-slate-950/70 p-4 text-center text-xs text-white/50"
    >
      You don’t have permission to post messages on this project.
    </div>
  </div>
</template>
