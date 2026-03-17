<script setup lang="ts">
type CustomFieldDef = {
  id: number
  label: string
  slug: string
  type: string
  options?: string[] | null
  is_required: boolean
}

const props = defineProps<{
  fields: CustomFieldDef[]
  modelValue: Record<string, string | number | string[] | null>
  errors?: Record<string, string>
}>()

const emit = defineEmits<{
  'update:modelValue': [v: Record<string, string | number | string[] | null>]
}>()

const inputClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'
const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1'

function getValue(slug: string): string | number | string[] | null {
  return props.modelValue[slug] ?? null
}

function setValue(slug: string, value: string | number | string[] | null) {
  emit('update:modelValue', { ...props.modelValue, [slug]: value })
}

function setMultiselect(slug: string, option: string, checked: boolean) {
  const arr = Array.isArray(props.modelValue[slug]) ? [...(props.modelValue[slug] as string[])] : []
  if (checked) {
    if (!arr.includes(option)) arr.push(option)
  } else {
    const i = arr.indexOf(option)
    if (i >= 0) arr.splice(i, 1)
  }
  emit('update:modelValue', { ...props.modelValue, [slug]: arr })
}

function isMultiselectChecked(slug: string, option: string): boolean {
  const arr = props.modelValue[slug]
  return Array.isArray(arr) && arr.includes(option)
}
</script>

<template>
  <div v-if="fields.length" class="border-t border-white/10 pt-6 space-y-4">
    <h2 class="text-sm font-semibold text-white/80">
      Custom Fields
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="f in fields" :key="f.id">
        <label :class="labelClass">
          {{ f.label }}
          <span v-if="f.is_required" class="text-red-400">*</span>
        </label>
        <!-- text -->
        <input
          v-if="f.type === 'text'"
          :value="getValue(f.slug) ?? ''"
          type="text"
          :class="inputClass"
          :placeholder="f.label"
          @input="setValue(f.slug, ($event.target as HTMLInputElement).value || null)"
        />
        <!-- number -->
        <input
          v-else-if="f.type === 'number'"
          :value="getValue(f.slug) ?? ''"
          type="number"
          :class="inputClass"
          :placeholder="f.label"
          step="any"
          @input="setValue(f.slug, ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null)"
        />
        <!-- date -->
        <input
          v-else-if="f.type === 'date'"
          :value="getValue(f.slug) ?? ''"
          type="date"
          :class="inputClass"
          @input="setValue(f.slug, ($event.target as HTMLInputElement).value || null)"
        />
        <!-- select -->
        <select
          v-else-if="f.type === 'select'"
          :value="getValue(f.slug) ?? ''"
          :class="inputClass"
          @change="setValue(f.slug, ($event.target as HTMLSelectElement).value || null)"
        >
          <option value="">— Select —</option>
          <option v-for="opt in (f.options ?? [])" :key="opt" :value="opt">
            {{ opt }}
          </option>
        </select>
        <!-- multiselect -->
        <div v-else-if="f.type === 'multiselect'" class="space-y-2">
          <label
            v-for="opt in (f.options ?? [])"
            :key="opt"
            class="flex items-center gap-2 cursor-pointer"
          >
            <input
              type="checkbox"
              :checked="isMultiselectChecked(f.slug, opt)"
              class="rounded border-white/20"
              @change="setMultiselect(f.slug, opt, ($event.target as HTMLInputElement).checked)"
            />
            <span class="text-sm text-white/80">{{ opt }}</span>
          </label>
        </div>
        <div v-else>
          <input
            :value="getValue(f.slug) ?? ''"
            type="text"
            :class="inputClass"
            @input="setValue(f.slug, ($event.target as HTMLInputElement).value || null)"
          />
        </div>
        <div v-if="$page.props.errors && ($page.props.errors as Record<string,string>)[`custom_values.${f.slug}`]" class="mt-1 text-sm text-red-400">
          {{ ($page.props.errors as Record<string,string>)[`custom_values.${f.slug}`] }}
        </div>
      </div>
    </div>
  </div>
</template>
