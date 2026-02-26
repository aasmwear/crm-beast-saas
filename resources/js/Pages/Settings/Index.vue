<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ChromeTabs from '@/Components/ui/ChromeTabs.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'

defineOptions({ layout: AuthenticatedLayout })

type WorkHours = {
  work_week?: string
  start_time?: string
  end_time?: string
}

type NotificationDefaults = {
  channels: { inapp: boolean; email: boolean }
  types?: Record<string, boolean>
}

const props = defineProps<{
  organization: {
    id: number
    name: string
    slug: string
    logo_path: string | null
    timezone: string
    week_start: string
  }
  settings?: {
    locale?: string
    currency?: string
    work_hours?: WorkHours
    notifications_defaults?: NotificationDefaults
    slack_webhook_url?: string | null
    slack_webhook_connected?: boolean
    smtp_host?: string | null
    smtp_port?: number | null
    smtp_user?: string | null
    smtp_from?: string | null
    smtp_pass_set?: boolean
  }
  timezones: string[]
  locales?: Record<string, string>
  currencies?: Record<string, string>
}>()

const page = usePage<{ flash?: { success?: string } }>()
const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, string> = {}) =>
  routeGlobal ? routeGlobal(name, params) : '#'

const org = computed(() => props.organization?.slug ?? 'acme')

// Secrets are never in props; use empty string for form. slack_webhook_url is masked (••••••••) when connected.
const form = useForm({
  name: props.organization.name,
  timezone: props.organization.timezone,
  week_start: props.organization.week_start,
  logo: null as File | null,
  locale: props.settings?.locale ?? 'en',
  currency: props.settings?.currency ?? 'USD',
  work_hours: {
    work_week: props.settings?.work_hours?.work_week ?? 'Mon-Fri',
    start_time: props.settings?.work_hours?.start_time ?? '09:00',
    end_time: props.settings?.work_hours?.end_time ?? '17:00',
  } as WorkHours,
  notifications_defaults: {
    channels: {
      inapp: props.settings?.notifications_defaults?.channels?.inapp ?? true,
      email: props.settings?.notifications_defaults?.channels?.email ?? false,
    },
    types: props.settings?.notifications_defaults?.types ?? {},
  } as NotificationDefaults,
  slack_webhook_url: '' as string,
  smtp_host: props.settings?.smtp_host ?? '',
  smtp_port: props.settings?.smtp_port ?? null as number | null,
  smtp_user: props.settings?.smtp_user ?? '',
  smtp_pass: '' as string,
  smtp_from: props.settings?.smtp_from ?? '',
})

const activeTab = ref<string>('organization')
const logoPreview = ref<string | null>(null)
const logoDropActive = ref(false)
const slackWebhookClear = ref(false)
const testSlackLoading = ref(false)
const testSlackToast = ref<{ type: 'success' | 'error'; msg: string } | null>(null)
const testSmtpLoading = ref(false)
const testSmtpToast = ref<{ type: 'success' | 'error'; msg: string } | null>(null)

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

form.transform((data) => {
  if (props.settings?.slack_webhook_connected && data.slack_webhook_url === '' && !slackWebhookClear.value) {
    delete (data as Record<string, unknown>).slack_webhook_url
  }
  if (slackWebhookClear.value) {
    (data as Record<string, unknown>).slack_webhook_url = ''
  }
  return data
})

function submit() {
  form.post(r('settings.update', { organization: org.value }), {
    forceFormData: true,
    onSuccess: () => {
      form.logo = null
      form.smtp_pass = ''
      logoPreview.value = null
      slackWebhookClear.value = false
    },
  })
}

function clearSlackWebhook() {
  form.slack_webhook_url = ''
  slackWebhookClear.value = true
}

const csrfToken = () =>
  (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

async function testSlack() {
  testSlackLoading.value = true
  testSlackToast.value = null
  try {
    const res = await fetch(r('settings.testSlack', { organization: org.value }), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (json.success) {
      testSlackToast.value = { type: 'success', msg: json.message ?? 'Slack webhook test sent.' }
    } else {
      testSlackToast.value = { type: 'error', msg: json.message ?? 'Test failed.' }
    }
  } catch (e) {
    testSlackToast.value = { type: 'error', msg: (e as Error).message ?? 'Request failed.' }
  } finally {
    testSlackLoading.value = false
    setTimeout(() => { testSlackToast.value = null }, 4000)
  }
}

async function testSmtp() {
  testSmtpLoading.value = true
  testSmtpToast.value = null
  try {
    const res = await fetch(r('settings.testSmtp', { organization: org.value }), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (json.success) {
      testSmtpToast.value = { type: 'success', msg: json.message ?? 'SMTP connection successful.' }
    } else {
      testSmtpToast.value = { type: 'error', msg: json.message ?? 'Test failed.' }
    }
  } catch (e) {
    testSmtpToast.value = { type: 'error', msg: (e as Error).message ?? 'Request failed.' }
  } finally {
    testSmtpLoading.value = false
    setTimeout(() => { testSmtpToast.value = null }, 4000)
  }
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

const tabs = [
  { key: 'organization', label: 'Organization' },
  { key: 'branding', label: 'Branding' },
  { key: 'work_hours', label: 'Work Hours' },
  { key: 'notifications', label: 'Notification Defaults' },
  { key: 'integrations', label: 'Integrations' },
]
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      :breadcrumb="`Organization • ${org.toUpperCase()}`"
      title="Settings"
      subtitle="Configure your organization branding, localization, work hours, and integrations."
    />

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
      <ChromeTabs
        v-model="activeTab"
        :tabs="tabs"
        local
      />

      <!-- Organization -->
      <section v-show="activeTab === 'organization'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Organization
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
          <label :class="labelClass">Slug</label>
          <input
            :value="organization.slug"
            type="text"
            :class="inputClass"
            disabled
          >
          <p class="mt-1 text-xs text-white/50">
            Read-only. Used in URLs.
          </p>
        </div>
        <div>
          <label :class="labelClass">Timezone</label>
          <select v-model="form.timezone" :class="inputClass" required>
            <option v-for="tz in timezones" :key="tz" :value="tz">
              {{ tz }}
            </option>
          </select>
          <p v-if="form.errors.timezone" class="mt-1 text-sm text-red-400">
            {{ form.errors.timezone }}
          </p>
        </div>
        <div>
          <label :class="labelClass">Start of week</label>
          <select v-model="form.week_start" :class="inputClass" required>
            <option value="Monday">Monday</option>
            <option value="Sunday">Sunday</option>
          </select>
          <p v-if="form.errors.week_start" class="mt-1 text-sm text-red-400">
            {{ form.errors.week_start }}
          </p>
        </div>
        <div>
          <label :class="labelClass">Locale</label>
          <select v-model="form.locale" :class="inputClass">
            <option v-for="(label, code) in (locales ?? { en: 'English' })" :key="code" :value="code">
              {{ label }}
            </option>
          </select>
        </div>
        <div>
          <label :class="labelClass">Currency</label>
          <select v-model="form.currency" :class="inputClass">
            <option v-for="(label, code) in (currencies ?? { USD: 'USD' })" :key="code" :value="code">
              {{ label }}
            </option>
          </select>
        </div>
      </section>

      <!-- Branding -->
      <section v-show="activeTab === 'branding'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Branding
        </h2>
        <div>
          <label :class="labelClass">Logo</label>
          <div class="mt-1 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
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
              <span v-else class="text-xs text-white/40">Drop or click</span>
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
                <input type="file" accept="image/*" class="hidden" @change="onFileChange">
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

      <!-- Work Hours -->
      <section v-show="activeTab === 'work_hours'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Work Hours
        </h2>
        <p class="text-xs text-white/50">
          Used for attendance and reporting. Defines the default work week and daily hours.
        </p>
        <div>
          <label :class="labelClass">Work week</label>
          <select v-model="form.work_hours.work_week" :class="inputClass">
            <option value="Mon-Fri">Monday – Friday</option>
            <option value="Sun-Thu">Sunday – Thursday</option>
          </select>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label :class="labelClass">Start time</label>
            <input
              v-model="form.work_hours.start_time"
              type="time"
              :class="inputClass"
            >
            <p v-if="form.errors['work_hours.start_time']" class="mt-1 text-sm text-red-400">
              {{ form.errors['work_hours.start_time'] }}
            </p>
          </div>
          <div>
            <label :class="labelClass">End time</label>
            <input
              v-model="form.work_hours.end_time"
              type="time"
              :class="inputClass"
            >
            <p v-if="form.errors['work_hours.end_time']" class="mt-1 text-sm text-red-400">
              {{ form.errors['work_hours.end_time'] }}
            </p>
          </div>
        </div>
      </section>

      <!-- Notification Defaults -->
      <section v-show="activeTab === 'notifications'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Notification Defaults
        </h2>
        <p class="text-xs text-white/50">
          Default preferences for new users. Individual users can override in their notification settings.
        </p>
        <div class="space-y-3">
          <label class="flex items-center gap-3">
            <input v-model="form.notifications_defaults.channels.inapp" type="checkbox" class="rounded">
            <span>In-app notifications</span>
          </label>
          <label class="flex items-center gap-3">
            <input v-model="form.notifications_defaults.channels.email" type="checkbox" class="rounded">
            <span>Email notifications</span>
          </label>
        </div>
      </section>

      <!-- Integrations -->
      <section v-show="activeTab === 'integrations'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Integrations
        </h2>
        <p class="text-xs text-white/50">
          Optional webhooks and SMTP for notifications and alerts.
        </p>

        <!-- Slack -->
        <div>
          <label :class="labelClass">Slack webhook URL</label>
          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="settings?.slack_webhook_connected"
              class="inline-flex items-center rounded-lg bg-emerald-500/20 px-2 py-1 text-xs font-medium text-emerald-400"
            >
              Connected
            </span>
            <span
              v-else
              class="inline-flex items-center rounded-lg bg-white/10 px-2 py-1 text-xs text-white/50"
            >
              Not set
            </span>
            <button
              v-if="settings?.slack_webhook_connected"
              type="button"
              class="rounded-lg border border-white/20 px-2 py-1 text-xs text-white/60 hover:text-white/90"
              @click="clearSlackWebhook"
            >
              Clear
            </button>
          </div>
          <input
            v-model="form.slack_webhook_url"
            type="url"
            :class="inputClass"
            :placeholder="settings?.slack_webhook_connected ? 'Enter new URL to replace' : 'https://hooks.slack.com/...'"
            class="mt-2"
          >
          <div class="mt-2 flex items-center gap-2">
            <button
              type="button"
              class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-xs font-medium text-white/80 hover:bg-white/10 disabled:opacity-50"
              :disabled="testSlackLoading || !settings?.slack_webhook_connected"
              @click="testSlack"
            >
              {{ testSlackLoading ? 'Testing…' : 'Test Slack' }}
            </button>
            <span
              v-if="testSlackToast"
              class="text-xs"
              :class="testSlackToast.type === 'success' ? 'text-emerald-400' : 'text-red-400'"
            >
              {{ testSlackToast.msg }}
            </span>
          </div>
          <p v-if="form.errors.slack_webhook_url" class="mt-1 text-sm text-red-400">
            {{ form.errors.slack_webhook_url }}
          </p>
        </div>

        <!-- SMTP -->
        <div class="border-t border-white/10 pt-4">
          <h3 class="text-xs font-medium text-white/70 mb-3">
            SMTP (optional)
          </h3>
          <div class="mb-2 flex items-center gap-2">
            <span
              v-if="settings?.smtp_host"
              class="inline-flex items-center rounded-lg bg-emerald-500/20 px-2 py-1 text-xs font-medium text-emerald-400"
            >
              Configured
            </span>
            <span
              v-else
              class="inline-flex items-center rounded-lg bg-white/10 px-2 py-1 text-xs text-white/50"
            >
              Not set
            </span>
            <span
              v-if="settings?.smtp_host"
              class="text-xs text-white/50"
            >
              Password: {{ settings?.smtp_pass_set ? '••••••••' : 'Not set' }}
            </span>
          </div>
          <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label :class="labelClass">Host</label>
                <input v-model="form.smtp_host" type="text" :class="inputClass" placeholder="smtp.example.com">
              </div>
              <div>
                <label :class="labelClass">Port</label>
                <input v-model.number="form.smtp_port" type="number" :class="inputClass" placeholder="587">
              </div>
            </div>
            <div>
              <label :class="labelClass">From address</label>
              <input v-model="form.smtp_from" type="text" :class="inputClass" placeholder="noreply@example.com">
            </div>
            <div>
              <label :class="labelClass">Username</label>
              <input v-model="form.smtp_user" type="text" :class="inputClass">
            </div>
            <div>
              <label :class="labelClass">Password</label>
              <input v-model="form.smtp_pass" type="password" :class="inputClass" placeholder="Leave blank to keep current">
            </div>
            <div v-if="settings?.smtp_host" class="flex items-center gap-2">
              <button
                type="button"
                class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-xs font-medium text-white/80 hover:bg-white/10 disabled:opacity-50"
                :disabled="testSmtpLoading"
                @click="testSmtp"
              >
                {{ testSmtpLoading ? 'Testing…' : 'Test SMTP' }}
              </button>
              <span
                v-if="testSmtpToast"
                class="text-xs"
                :class="testSmtpToast.type === 'success' ? 'text-emerald-400' : 'text-red-400'"
              >
                {{ testSmtpToast.msg }}
              </span>
            </div>
          </div>
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
