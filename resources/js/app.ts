import './bootstrap'
import '../css/app.css'
import '../css/theme.css'

import { createApp, h, type ComponentCustomProperties } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { route as ziggyRoute } from 'ziggy-js'

declare global {
  interface Window { route: typeof ziggyRoute }
}
declare module 'vue' {
  interface ComponentCustomProperties {
    route: typeof ziggyRoute
  }
}

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })

createInertiaApp({
  resolve: (name) => {
    const mod = (pages as Record<string, any>)[`./Pages/${name}.vue`]
    if (!mod) throw new Error(`Page not found: ${name}`)
    return mod
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) }).use(plugin)
    app.config.globalProperties.route = ziggyRoute
    window.route = ziggyRoute
    app.mount(el)
  },
  progress: { color: '#16a34a' },
})
