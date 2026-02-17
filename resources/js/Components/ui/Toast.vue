<script setup lang="ts">
import { watch, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage<{ flash?: { success?: string; error?: string } }>()
const visible = ref(false)
const message = ref('')
const type = ref<'success' | 'error'>('success')
let timeoutId: ReturnType<typeof setTimeout> | null = null

watch(
  () => ({ ...page.props.flash }),
  (flash) => {
    if (timeoutId) clearTimeout(timeoutId)
    if (flash?.success) {
      message.value = flash.success
      type.value = 'success'
      visible.value = true
      timeoutId = setTimeout(() => {
        visible.value = false
        timeoutId = null
      }, 3000)
    } else if (flash?.error) {
      message.value = flash.error
      type.value = 'error'
      visible.value = true
      timeoutId = setTimeout(() => {
        visible.value = false
        timeoutId = null
      }, 3000)
    }
  },
  { immediate: true, deep: true },
)
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-4"
    >
      <div
        v-if="visible && message"
        class="fixed bottom-6 right-6 z-[100] flex max-w-sm rounded-xl border px-4 py-3 shadow-2xl backdrop-blur-md"
        :class="
          type === 'success'
            ? 'border-emerald-500/50 bg-slate-900/95 text-emerald-100'
            : 'border-red-500/50 bg-slate-900/95 text-red-100'
        "
        role="alert"
      >
        <div class="flex items-center gap-3">
          <span
            v-if="type === 'success'"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20"
          >
            <svg class="h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </span>
          <span
            v-else
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-500/20"
          >
            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </span>
          <p class="text-sm font-medium">
            {{ message }}
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
