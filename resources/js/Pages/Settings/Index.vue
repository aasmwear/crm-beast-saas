<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

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
  features?: Record<string, boolean | number>
  featureCatalog?: Array<{ key: string; label: string; description: string; type: string; default: boolean | number }>
  canViewApiKeys?: boolean
  apiKeys?: Array<{
    id: number
    name: string
    prefix: string
    created_at: string
    created_by?: string
    last_used_at?: string
    revoked_at?: string
  }>
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
function buildFeaturesForm(): Record<string, boolean | number> {
  const catalog = props.featureCatalog ?? []
  const defaults: Record<string, boolean | number> = {}
  for (const item of catalog) {
    defaults[item.key] = item.default
  }
  return { ...defaults, ...(props.features ?? {}) }
}
const featuresForm = ref<Record<string, boolean | number>>(buildFeaturesForm())

// API Keys
const apiKeys = computed(() => props.apiKeys ?? [])
const apiKeysCreateModalOpen = ref(false)
const apiKeysCreateName = ref('')
const apiKeysCreateLoading = ref(false)
const apiKeysCreateError = ref<string | null>(null)
const apiKeysNewToken = ref<string | null>(null)
const apiKeysNewKeyId = ref<number | null>(null)
const apiKeysRevokeLoading = ref<number | null>(null)
watch(
  () => [props.features, props.featureCatalog],
  () => {
    featuresForm.value = buildFeaturesForm()
  },
  { immediate: true },
)
const featuresLoading = ref(false)
const featuresToast = ref<{ type: 'success' | 'error'; msg: string } | null>(null)

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
  const d = data as Record<string, unknown>
  if (props.settings?.slack_webhook_connected && d.slack_webhook_url === '' && !slackWebhookClear.value) {
    delete d.slack_webhook_url
  }
  if (slackWebhookClear.value) {
    d.slack_webhook_url = ''
    d.slack_webhook_clear = true
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

async function createApiKey() {
  if (!apiKeysCreateName.value.trim()) return
  apiKeysCreateLoading.value = true
  apiKeysCreateError.value = null
  try {
    const res = await fetch(r('settings.api-keys.store', { organization: org.value }), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ name: apiKeysCreateName.value.trim() }),
    })
    const json = await res.json()
    if (json.success && json.plaintext_token) {
      apiKeysNewToken.value = json.plaintext_token
      apiKeysNewKeyId.value = json.api_key?.id ?? null
      apiKeysCreateModalOpen.value = false
      apiKeysCreateName.value = ''
      router.reload()
    } else {
      apiKeysCreateError.value = json.message ?? 'Failed to create API key.'
    }
  } catch (e) {
    apiKeysCreateError.value = (e as Error).message ?? 'Request failed.'
  } finally {
    apiKeysCreateLoading.value = false
  }
}

function copyApiKeyToken() {
  if (!apiKeysNewToken.value) return
  navigator.clipboard.writeText(apiKeysNewToken.value).then(() => {
    apiKeysNewToken.value = null
    apiKeysNewKeyId.value = null
  })
}

function dismissNewToken() {
  apiKeysNewToken.value = null
  apiKeysNewKeyId.value = null
  router.reload()
}

async function revokeApiKey(id: number) {
  if (!confirm('Revoke this API key? It will stop working immediately.')) return
  apiKeysRevokeLoading.value = id
  try {
    const res = await fetch(r('settings.api-keys.destroy', { organization: org.value, apiKey: String(id) }), {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (json.success) {
      router.reload()
    }
  } finally {
    apiKeysRevokeLoading.value = null
  }
}

async function saveFeatures() {
  featuresLoading.value = true
  featuresToast.value = null
  try {
    const res = await fetch(r('settings.features', { organization: org.value }), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ features: featuresForm.value }),
    })
    const json = await res.json()
    if (json.success) {
      featuresToast.value = { type: 'success', msg: json.message ?? 'Feature flags updated.' }
    } else {
      featuresToast.value = { type: 'error', msg: json.message ?? 'Update failed.' }
    }
  } catch (e) {
    featuresToast.value = { type: 'error', msg: (e as Error).message ?? 'Request failed.' }
  } finally {
    featuresLoading.value = false
    setTimeout(() => { featuresToast.value = null }, 4000)
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

const visibleTabs = computed(() => {
  const list = [
    { key: 'organization', label: 'Organization' },
    { key: 'branding', label: 'Branding' },
    { key: 'work_hours', label: 'Work Hours' },
    { key: 'notifications', label: 'Notification Defaults' },
    { key: 'integrations', label: 'Integrations' },
    { key: 'modules', label: 'Modules' },
    { key: 'api_keys', label: 'API Keys' },
  ]
  return props.canViewApiKeys ? list : list.filter(t => t.key !== 'api_keys')
})
</script>

<template>
  <PageShell
    v-model="activeTab"
    :tabs="visibleTabs"
    :sticky="true"
    :header="{
      breadcrumb: `Organization • ${org.toUpperCase()}`,
      title: 'Settings',
      subtitle: 'Configure your organization branding, localization, work hours, and integrations.',
    }"
  >
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

      <!-- Modules / Feature Flags -->
      <section v-show="activeTab === 'modules'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          Modules / Feature Flags
        </h2>
        <p class="text-xs text-white/50">
          Enable or disable modules and configure feature limits per organization.
        </p>
        <div
          v-for="item in (featureCatalog ?? [])"
          :key="item.key"
          class="flex flex-col gap-2 border-b border-white/10 pb-4 last:border-0 last:pb-0"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <label :class="labelClass">{{ item.label }}</label>
              <p class="text-xs text-white/50">
                {{ item.description }}
              </p>
            </div>
            <div v-if="item.type === 'boolean'" class="shrink-0">
              <label class="flex cursor-pointer items-center gap-2">
                <input
                  v-model="featuresForm[item.key]"
                  type="checkbox"
                  class="rounded"
                >
                <span class="text-sm text-white/70">{{ featuresForm[item.key] ? 'On' : 'Off' }}</span>
              </label>
            </div>
            <div v-else-if="item.type === 'number'" class="w-24 shrink-0">
              <input
                v-model.number="featuresForm[item.key]"
                type="number"
                min="0"
                :class="inputClass"
              >
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2 pt-2">
          <button
            type="button"
            class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90 disabled:opacity-50"
            :disabled="featuresLoading"
            @click="saveFeatures"
          >
            {{ featuresLoading ? 'Saving…' : 'Save feature flags' }}
          </button>
          <span
            v-if="featuresToast"
            class="text-xs"
            :class="featuresToast.type === 'success' ? 'text-emerald-400' : 'text-red-400'"
          >
            {{ featuresToast.msg }}
          </span>
        </div>
      </section>

      <!-- API Keys -->
      <section v-show="activeTab === 'api_keys'" class="card-neo p-6 space-y-6">
        <h2 class="text-sm font-semibold text-white/80">
          API Keys
        </h2>
        <p class="text-xs text-white/50">
          Create and manage API keys for integrations. Keys are shown in full only once when created.
        </p>

        <!-- One-time token display -->
        <div
          v-if="apiKeysNewToken"
          class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4"
        >
          <p class="text-xs font-medium text-amber-400 mb-2">
            Copy your key now — it won't be shown again.
          </p>
          <div class="flex flex-wrap items-center gap-2">
            <code class="flex-1 min-w-0 rounded bg-black/30 px-2 py-2 text-sm text-white/90 break-all font-mono">
              {{ apiKeysNewToken }}
            </code>
            <button
              type="button"
              class="shrink-0 rounded-xl border border-amber-500/50 bg-amber-500/20 px-3 py-2 text-sm font-medium text-amber-200 hover:bg-amber-500/30"
              @click="copyApiKeyToken"
            >
              Copy
            </button>
            <button
              type="button"
              class="shrink-0 rounded-xl border border-white/20 px-3 py-2 text-sm text-white/70 hover:text-white/90"
              @click="dismissNewToken"
            >
              Done
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between gap-4">
          <span class="text-xs text-white/50">
            {{ apiKeys.length }} key(s)
          </span>
          <button
            type="button"
            class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
            @click="apiKeysCreateModalOpen = true; apiKeysCreateError = null; apiKeysCreateName = ''"
          >
            Create API key
          </button>
        </div>

        <div v-if="apiKeys.length === 0" class="rounded-xl border border-white/10 bg-white/5 p-6 text-center text-sm text-white/50">
          No API keys yet. Create one to get started.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 text-left text-xs text-white/50">
                <th class="pb-2 pr-4">Name</th>
                <th class="pb-2 pr-4">Prefix</th>
                <th class="pb-2 pr-4">Created</th>
                <th class="pb-2 pr-4">Created by</th>
                <th class="pb-2 pr-4">Last used</th>
                <th class="pb-2 pr-4">Status</th>
                <th class="pb-2 pl-2" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="k in apiKeys"
                :key="k.id"
                class="border-b border-white/5"
              >
                <td class="py-3 pr-4 font-medium text-white/90">{{ k.name }}</td>
                <td class="py-3 pr-4 font-mono text-white/70">{{ k.prefix }}…</td>
                <td class="py-3 pr-4 text-white/60">{{ new Date(k.created_at).toLocaleString() }}</td>
                <td class="py-3 pr-4 text-white/60">{{ k.created_by ?? '—' }}</td>
                <td class="py-3 pr-4 text-white/60">{{ k.last_used_at ? new Date(k.last_used_at).toLocaleString() : '—' }}</td>
                <td class="py-3 pr-4">
                  <span
                    v-if="k.revoked_at"
                    class="inline-flex rounded-lg bg-red-500/20 px-2 py-0.5 text-xs text-red-400"
                  >
                    Revoked
                  </span>
                  <span
                    v-else
                    class="inline-flex rounded-lg bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400"
                  >
                    Active
                  </span>
                </td>
                <td class="py-3 pl-2">
                  <button
                    v-if="!k.revoked_at"
                    type="button"
                    class="rounded-lg border border-red-500/30 px-2 py-1 text-xs text-red-400 hover:bg-red-500/10 disabled:opacity-50"
                    :disabled="apiKeysRevokeLoading === k.id"
                    @click="revokeApiKey(k.id)"
                  >
                    {{ apiKeysRevokeLoading === k.id ? 'Revoking…' : 'Revoke' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Create API Key Modal -->
      <Teleport to="body">
        <div
          v-if="apiKeysCreateModalOpen"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          @click.self="apiKeysCreateModalOpen = false"
        >
          <div class="card-neo mx-4 w-full max-w-md p-6 shadow-xl">
            <h3 class="text-sm font-semibold text-white/90 mb-4">
              Create API Key
            </h3>
            <div>
              <label :class="labelClass">Name</label>
              <input
                v-model="apiKeysCreateName"
                type="text"
                :class="inputClass"
                placeholder="e.g. Production integration"
                @keydown.enter.prevent="createApiKey"
              >
            </div>
            <p v-if="apiKeysCreateError" class="mt-2 text-sm text-red-400">
              {{ apiKeysCreateError }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
              <button
                type="button"
                class="rounded-xl border border-white/20 px-4 py-2 text-sm text-white/70 hover:text-white/90"
                @click="apiKeysCreateModalOpen = false"
              >
                Cancel
              </button>
              <button
                type="button"
                class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90 disabled:opacity-50"
                :disabled="apiKeysCreateLoading || !apiKeysCreateName.trim()"
                @click="createApiKey"
              >
                {{ apiKeysCreateLoading ? 'Creating…' : 'Create' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

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
  </PageShell>
</template>
