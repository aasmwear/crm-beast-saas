// resources/js/types/vue-global.d.ts
import type { ComponentCustomProperties } from 'vue'

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    /**
     * Make the real browser window available in templates as `window`.
     * We assume it's always set in app bootstrap:
     *   app.config.globalProperties.window = window
     */
    window: Window & { tenant?: { slug?: string } }
  }
}
export {}
