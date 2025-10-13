import './bootstrap'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import { ZiggyVue } from 'ziggy'

// Cast the glob so TS is happy when we index it.
const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, any>

createInertiaApp({
  resolve: (name: string) => {
    const mod = pages[`./Pages/${name}.vue`]
    if (!mod) throw new Error(`Page not found: ${name}`)
    return mod.default ?? mod
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App as any, props as any) })
      .use(plugin)
      .use(ZiggyVue, (window as any).Ziggy ?? undefined) // NOTE: uppercase Ziggy

    app.config.globalProperties.route = route
    app.mount(el)
    return app
  },
  progress: { color: '#8B7CFF' },
})
