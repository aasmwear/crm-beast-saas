<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'

type CommentItem = {
  id: number
  body: string
  created_at: string | null
  user: { id: number; name: string } | null
}

const props = defineProps<{
  comments: CommentItem[]
  postUrl: string
  organizationSlug: string
  currentUserId?: number
  canDelete?: (c: CommentItem) => boolean
}>()

const routeGlobal = (window as any).route as ((name: string, params?: Record<string, unknown>) => string) | undefined

const form = useForm<{ body: string }>({ body: '' })

function submitComment() {
  if (!form.body.trim()) return
  form.post(props.postUrl, {
    preserveScroll: true,
    onSuccess: () => form.reset('body'),
  })
}

function deleteComment(commentId: number) {
  if (!confirm('Delete this comment?')) return
  const url = routeGlobal?.('comments.destroy', {
    comment: commentId,
    organization: props.organizationSlug,
  })
  if (url) {
    router.delete(url, { preserveScroll: true })
  }
}

function formatTime(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`
  return d.toLocaleDateString()
}
</script>

<template>
  <div class="space-y-4">
    <h3 class="text-sm font-semibold text-white">
      Comments
    </h3>

    <!-- Post comment -->
    <form class="space-y-2" @submit.prevent="submitComment">
      <textarea
        v-model="form.body"
        rows="2"
        placeholder="Write a comment..."
        class="w-full rounded-lg border border-white/10 bg-slate-900/80 px-3 py-2 text-xs text-white placeholder:text-white/40 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400/40"
      />
      <p v-if="form.errors.body" class="text-[11px] text-red-400">
        {{ form.errors.body }}
      </p>
      <button
        type="submit"
        class="inline-flex items-center rounded-full bg-indigo-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-400 disabled:opacity-50"
        :disabled="form.processing"
      >
        {{ form.processing ? 'Posting...' : 'Post comment' }}
      </button>
    </form>

    <!-- Comment list -->
    <div v-if="comments.length > 0" class="space-y-3">
      <div
        v-for="c in comments"
        :key="c.id"
        class="flex gap-3 rounded-lg border border-white/5 bg-slate-800/30 px-3 py-2"
      >
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500/30 text-xs font-medium text-indigo-200">
          {{ c.user?.name?.charAt(0) ?? '?' }}
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 text-[11px]">
            <span class="font-medium text-white">
              {{ c.user?.name ?? 'Unknown' }}
            </span>
            <span class="text-white/40">
              {{ formatTime(c.created_at) }}
            </span>
            <button
              v-if="canDelete?.(c)"
              type="button"
              class="ml-auto text-white/40 hover:text-red-400"
              title="Delete (author or admin)"
              @click="deleteComment(c.id)"
            >
              Delete
            </button>
          </div>
          <p class="mt-0.5 whitespace-pre-wrap text-xs text-white/80">
            {{ c.body }}
          </p>
        </div>
      </div>
    </div>
    <p v-else class="text-xs text-white/50">
      No comments yet.
    </p>
  </div>
</template>
