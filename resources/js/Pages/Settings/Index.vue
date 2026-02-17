<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps<{
  organization: {
    id: number
    name: string
    slug: string
    logo_path: string | null
    timezone: string
    week_start: string
  }
  timezones: string[]
}>()

const page = usePage<{ flash?: { success?: string } }>()
const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, string> = {}) =>
  routeGlobal ? routeGlobal(name, params) : '#'

const org = computed(() => props.organization?.slug ?? 'acme')

const form = useForm({
  name: props.organization.name,
  timezone: props.organization.timezone,
  week_start: props.organization.week_start,
  logo: null as File | null,
})

const logoPreview = ref<string | null>(null)
const logoDropActive = ref(false)

function logoUrl(path: string | null): string | null {
  if (!path) return null
  return `/storage/${path}`
}

const currentLogoUrl = computed(() => logoUrl(props.organization.logo_path))

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) {
    form.logo = file
    logoPreview.value = URL.createObjectURL(file)
  }
  input.value = ''
}

function onDrop(e: DragEvent) {
  logoDropActive.value = false
  e.preventDefault()
  const file = e.dataTransfer?.files?.[0]
  if (file && file.type.startsWith('image/')) {
    form.logo = file
    logoPreview.value = URL.createObjectURL(file)
  }
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  logoDropActive.value = true
}

function onDragLeave() {
  logoDropActive.value = false
}

function removeLogo() {
  form.logo = null
  logoPreview.value = null
}

const displayLogoUrl = computed(() => logoPreview.value ?? currentLogoUrl.value)

function submit() {
  form.post(r('settings.update', { organization: org.value }), {
    forceFormData: true,
    onSuccess: () => {
      form.logo = null
      logoPreview.value = null
    },
  })
}

const showToast = ref(false)
watch(
  () => page.props.flash?.success,
  (success) => {
    if (success) {
      showToast.value = true
      setTimeout(() => {
        showToast.value = false
      }, 3000)
    }
  },
  { immediate: true },
)

const inputClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'
const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1'
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <section class="hero-slab">
      <div class="flex items-center justify-between gap-4">
        <div>
          <div class="text-xs text-white/60">
            Organization • {{ org.toUpperCase() }}
          </div>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Settings
          </h1>
          <p class="mt-1 text-white/60">
            Configure your organization branding and localization.
          </p>
        </div>
      </div>
    </section>

    <!-- Toast -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="showToast"
        class="fixed bottom-6 right-6 z-50 rounded-xl border border-white/10 bg-slate-800/95 px-4 py-3 text-sm font-medium text-white shadow-lg backdrop-blur"
      >
        Saved!
      </div>
    </Transition>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Brand Identity -->
      <section class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Brand Identity
        </h2>

        <div>
          <label :class="labelClass">Organization name</label>
          <input
            v-model="form.name"
            type="text"
            :class="inputClass"
            placeholder="Acme Inc."
            required
          >
          <p v-if="form.errors.name" class="mt-1 text-sm text-red-400">
            {{ form.errors.name }}
          </p>
        </div>

        <div>
          <label :class="labelClass">Logo</label>
          <div
            class="mt-1 flex flex-col items-start gap-4 sm:flex-row sm:items-center"
          >
            <div
              class="relative flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition"
              :class="
                logoDropActive
                  ? 'border-[var(--primary)] bg-violet-500/10'
                  : 'border-white/20 bg-white/5 hover:border-white/30'
              "
              @dragover="onDragOver"
              @dragleave="onDragLeave"
              @drop="onDrop"
            >
              <img
                v-if="displayLogoUrl"
                :src="displayLogoUrl"
                alt="Logo"
                class="h-full w-full object-contain p-1"
              >
              <span
                v-else
                class="text-xs text-white/40"
              >
                Drop or click
              </span>
              <input
                type="file"
                accept="image/*"
                class="absolute inset-0 cursor-pointer opacity-0"
                @change="onFileChange"
              >
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="cursor-pointer rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-xs font-medium text-white/80 hover:bg-white/10">
                Choose file
                <input
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onFileChange"
                >
              </label>
              <button
                v-if="displayLogoUrl"
                type="button"
                class="rounded-xl border border-white/20 px-3 py-2 text-xs text-white/60 hover:text-white/90"
                @click="removeLogo"
              >
                Remove
              </button>
            </div>
          </div>
          <p v-if="form.errors.logo" class="mt-1 text-sm text-red-400">
            {{ form.errors.logo }}
          </p>
          <p class="mt-1 text-xs text-white/50">
            Max 1 MB. Recommended: square image, at least 128×128.
          </p>
        </div>
      </section>

      <!-- Localization -->
      <section class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Localization
        </h2>

        <div>
          <label :class="labelClass">Timezone</label>
          <select
            v-model="form.timezone"
            :class="inputClass"
            required
          >
            <option
              v-for="tz in timezones"
              :key="tz"
              :value="tz"
            >
              {{ tz }}
            </option>
          </select>
          <p v-if="form.errors.timezone" class="mt-1 text-sm text-red-400">
            {{ form.errors.timezone }}
          </p>
        </div>

        <div>
          <label :class="labelClass">Start of week</label>
          <select
            v-model="form.week_start"
            :class="inputClass"
            required
          >
            <option value="Monday">
              Monday
            </option>
            <option value="Sunday">
              Sunday
            </option>
          </select>
          <p v-if="form.errors.week_start" class="mt-1 text-sm text-red-400">
            {{ form.errors.week_start }}
          </p>
        </div>
      </section>

      <!-- Roles & Permissions -->
      <section class="card-neo p-6 space-y-4">
        <h2 class="text-sm font-semibold text-white/80">
          Roles & Permissions
        </h2>
        <p class="text-sm text-white/60">
          Manage role-permission matrix for this organization.
        </p>
        <a
          :href="r('roles.index', { organization: org })"
          class="inline-flex items-center rounded-xl border border-white/20 bg-white/5 px-4 py-2 text-sm font-medium text-white/90 hover:bg-white/10"
        >
          Open Roles & Permissions →
        </a>
      </section>

      <!-- Submit -->
      <div class="flex justify-end">
        <button
          type="submit"
          class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Saving…' : 'Save settings' }}
        </button>
      </div>
    </form>
  </div>
</template>
