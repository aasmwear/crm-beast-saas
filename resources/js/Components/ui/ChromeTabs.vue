<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'

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
    tabs: ChromeTab[]
    modelValue?: string
    /** When true, use local mode (v-model). When false and tabs have href, use Inertia Link. */
    local?: boolean
  }>(),
  { local: false }
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [key: string]
}>()

const activeKey = computed({
  get: () => props.modelValue ?? props.tabs[0]?.key ?? '',
  set: (v: string) => {
    emit('update:modelValue', v)
    emit('change', v)
  },
})

const tabListRef = ref<HTMLElement | null>(null)
const focusedIndex = ref(0)

function setActive(key: string) {
  if (props.local) {
    activeKey.value = key
  }
  // If not local and tab has href, Link handles navigation
}

function onKeydown(e: KeyboardEvent, index: number) {
  if (e.key === 'ArrowLeft' && index > 0) {
    e.preventDefault()
    focusTab(index - 1)
  } else if (e.key === 'ArrowRight' && index < props.tabs.length - 1) {
    e.preventDefault()
    focusTab(index + 1)
  } else if (e.key === 'Home') {
    e.preventDefault()
    focusTab(0)
  } else if (e.key === 'End') {
    e.preventDefault()
    focusTab(props.tabs.length - 1)
  } else if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    const tab = props.tabs[index]
    if (tab && !tab.disabled) {
      if (props.local) setActive(tab.key)
      else if (tab.href) (e.target as HTMLElement).querySelector('a')?.click()
    }
  }
}

function focusTab(index: number) {
  focusedIndex.value = index
  const el = tabListRef.value?.querySelectorAll('[role="tab"]')[index] as HTMLElement
  el?.focus()
}

watch(
  () => activeKey.value,
  (key) => {
    const i = props.tabs.findIndex(t => t.key === key)
    if (i >= 0) focusedIndex.value = i
  },
  { immediate: true }
)
</script>

<template>
  <div
    class="chrome-tabs"
    role="tablist"
    aria-label="Tabs"
  >
    <div
      ref="tabListRef"
      class="chrome-tabs__scroll flex overflow-x-auto overflow-y-hidden pb-px -mb-px"
    >
      <template v-for="(tab, index) in tabs" :key="tab.key">
        <Link
          v-if="!local && tab.href"
          :href="tab.href"
          role="tab"
          :aria-selected="activeKey === tab.key"
          :aria-disabled="tab.disabled"
          :tabindex="activeKey === tab.key ? 0 : -1"
          class="chrome-tab"
          :class="{
            'chrome-tab--active': activeKey === tab.key,
            'chrome-tab--disabled': tab.disabled,
          }"
          @keydown="onKeydown($event, index)"
        >
          <span v-if="tab.icon" class="chrome-tab__icon mr-1.5" aria-hidden="true">
            {{ tab.icon }}
          </span>
          <span class="chrome-tab__label">{{ tab.label }}</span>
          <span v-if="tab.badge != null" class="chrome-tab__badge">
            {{ tab.badge }}
          </span>
        </Link>
        <button
          v-else
          type="button"
          role="tab"
          :aria-selected="activeKey === tab.key"
          :aria-disabled="tab.disabled"
          :tabindex="activeKey === tab.key ? 0 : -1"
          :disabled="tab.disabled"
          class="chrome-tab"
          :class="{
            'chrome-tab--active': activeKey === tab.key,
            'chrome-tab--disabled': tab.disabled,
          }"
          @click="setActive(tab.key)"
          @keydown="onKeydown($event, index)"
        >
          <span v-if="tab.icon" class="chrome-tab__icon mr-1.5" aria-hidden="true">
            {{ tab.icon }}
          </span>
          <span class="chrome-tab__label">{{ tab.label }}</span>
          <span v-if="tab.badge != null" class="chrome-tab__badge">
            {{ tab.badge }}
          </span>
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.chrome-tabs {
  --chrome-tab-height: 2.25rem;
  --chrome-tab-overlap: 8px;
  --chrome-tab-radius: 8px 8px 0 0;
  --chrome-tab-bg: rgba(30, 32, 42, 0.9);
  --chrome-tab-bg-active: rgba(15, 18, 28, 0.98);
  --chrome-tab-border: rgba(255, 255, 255, 0.08);
  --chrome-tab-border-active: rgba(255, 255, 255, 0.12);
}

.chrome-tabs__scroll {
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}

.chrome-tab {
  display: inline-flex;
  align-items: center;
  height: var(--chrome-tab-height);
  padding: 0 1rem;
  margin-right: calc(-1 * var(--chrome-tab-overlap));
  font-size: 0.8125rem;
  font-weight: 500;
  white-space: nowrap;
  border: 1px solid var(--chrome-tab-border);
  border-bottom: none;
  border-radius: var(--chrome-tab-radius);
  background: var(--chrome-tab-bg);
  color: rgba(255, 255, 255, 0.7);
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s, color 0.15s, border-color 0.15s, z-index 0.15s;
  position: relative;
  z-index: 1;
  outline: none;
}

.chrome-tab:first-child {
  margin-left: 0;
}

.chrome-tab:hover:not(.chrome-tab--disabled):not(.chrome-tab--active) {
  background: rgba(40, 42, 55, 0.95);
  color: rgba(255, 255, 255, 0.9);
}

.chrome-tab--active {
  background: var(--chrome-tab-bg-active);
  color: white;
  border-color: var(--chrome-tab-border-active);
  z-index: 2;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.2);
}

.chrome-tab:focus-visible {
  outline: 2px solid rgba(139, 92, 246, 0.6);
  outline-offset: 2px;
}

.chrome-tab--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.chrome-tab__badge {
  margin-left: 0.5rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.6875rem;
  font-weight: 600;
  border-radius: 9999px;
  background: rgba(139, 92, 246, 0.3);
  color: rgba(255, 255, 255, 0.95);
}

.chrome-tab--active .chrome-tab__badge {
  background: rgba(139, 92, 246, 0.5);
}

/* Mobile: ensure horizontal scroll works */
@media (max-width: 640px) {
  .chrome-tabs__scroll {
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x proximity;
  }
  .chrome-tab {
    scroll-snap-align: start;
    flex-shrink: 0;
  }
}
</style>
