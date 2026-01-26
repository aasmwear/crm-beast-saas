<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

// --- SAFE ROUTE LOGIC ---
const routeGlobal = (window as any).route
const r = (name: string, params: any = {}, absolute = false, config?: any) =>
  routeGlobal ? routeGlobal(name, params, absolute, config) : '#'

// Props from Inertia (optional slug for consistency with other pages)
const props = defineProps<{
  organizationSlug?: string
}>()

// Types for usePage
interface OrganizationProps {
  slug: string
}
interface FlashProps {
  success?: string
  error?: string
}
interface CustomPageProps {
  organization?: OrganizationProps
  flash?: FlashProps
  // allow extra keys
  [key: string]: any
}

// Apply types to usePage()
const page = usePage<CustomPageProps>()

const orgSlug = computed(() => {
  if (props.organizationSlug) {
    return props.organizationSlug
  }

  return page.props.organization?.slug ?? 'acme'
})

const form = useForm<{ csv: File | null }>({
  csv: null,
})

function submit() {
  form.post(
    r('clients.import.store', { organization: orgSlug.value }),
    { forceFormData: true },
  )
}

const inputClass =
  'w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white placeholder-white/50 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-white/10 file:text-[var(--primary)] hover:file:bg-white/20 transition'
</script>

<template>
  <div class="max-w-xl mx-auto py-6">
    <section class="mb-6">
      <h1 class="mt-1 text-3xl font-semibold tracking-tight">
        Import Clients (CSV)
      </h1>
      <p class="mt-1 text-white/60">
        Upload a CSV file to bulk import new clients.
      </p>
    </section>

    <div class="card-neo p-6">
      <form @submit.prevent="submit" class="space-y-4">
        <input
          type="file"
          accept=".csv,text/csv"
          :class="inputClass"
          @change="
            (e: any) => (form.csv = e.target.files?.[0] ?? null)
          "
        />

        <div v-if="form.errors.csv" class="text-red-400 text-sm">
          {{ form.errors.csv }}
        </div>

        <div class="pt-2">
          <button
            class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
            :disabled="form.processing || !form.csv"
          >
            {{ form.processing ? 'Uploading...' : 'Upload CSV' }}
          </button>
        </div>
      </form>

      <p
        v-if="page.props.flash?.success"
        class="mt-6 text-emerald-400"
      >
        {{ page.props.flash.success }}
      </p>
      <p
        v-if="page.props.flash?.error"
        class="mt-6 text-red-400"
      >
        {{ page.props.flash.error }}
      </p>
    </div>
  </div>
</template>
