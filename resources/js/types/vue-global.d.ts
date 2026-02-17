// resources/js/types/vue-global.d.ts
import type { ComponentCustomProperties } from 'vue'
import ziggyRoute from 'ziggy-js'
import type { PageProps as InertiaPageProps } from '@inertiajs/core'
import type { PageProps as AppPageProps } from './index'

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    /**
     * Make the real browser window available in templates as `window`.
     * We assume it's always set in app bootstrap:
     *   app.config.globalProperties.window = window
     */
    window: Window & { tenant?: { slug?: string } }
    route: typeof ziggyRoute
    $page: InertiaPageProps<AppPageProps>
  }
}
export {}
