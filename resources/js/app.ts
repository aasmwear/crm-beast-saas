import './bootstrap'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import route from 'ziggy-js'

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })

createInertiaApp({
  resolve: (name) => {
    const mod = pages[`./Pages/${name}.vue`]
    if (!mod) throw new Error(`Page not found: ${name}`)
    return (mod as any).default ?? mod
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) }).use(plugin)

    // Optional: expose in templates as `route(...)`
    app.config.globalProperties.route = route

    app.mount(el)
    return app
  },
  progress: { color: '#16a34a' },
})
