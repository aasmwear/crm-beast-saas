<script setup lang="ts">
import ChromeTabs from '@/Components/ui/ChromeTabs.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'

export interface ChromeTab {
  key: string
  label: string
  href?: string
  icon?: string
  badge?: string | number
  disabled?: boolean
}

const props = withDefaults(
  defineProps<{
    tabs?: ChromeTab[]
    modelValue?: string
    local?: boolean
    /** When true, tabs + header stick to top while scrolling (Chrome luxury mode) */
    sticky?: boolean
    header?: {
      breadcrumb?: string
      title: string
      subtitle?: string
    }
  }>(),
  { local: true, sticky: false }
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [key: string]
}>()

function onTabChange(key: string) {
  emit('update:modelValue', key)
  emit('change', key)
}
</script>

<template>
  <div class="space-y-4">
    <!-- 1) Sticky top area: ChromeTabs + PageHeader (when sticky=true, sticks to top while scrolling) -->
    <div
      :class="[
        'space-y-3',
        sticky && 'sticky top-0 z-40 bg-background backdrop-blur-sm border-b border-white/5',
      ]"
    >
      <div v-if="tabs && tabs.length > 0">
        <ChromeTabs
          :model-value="modelValue ?? tabs[0]?.key"
          :tabs="tabs"
          :local="local"
          @update:model-value="onTabChange"
        />
      </div>

      <PageHeader
        v-if="header"
        :breadcrumb="header.breadcrumb"
        :title="header.title"
        :subtitle="header.subtitle"
      >
        <template v-if="$slots['header-actions']" #actions>
          <slot name="header-actions" />
        </template>
      </PageHeader>
    </div>

    <!-- 2) Default slot content -->
    <div class="space-y-4">
      <slot />
    </div>
  </div>
</template>
