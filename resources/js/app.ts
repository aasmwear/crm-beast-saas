import './bootstrap'
import '../css/app.css'
import '../css/theme.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })

createInertiaApp({
  resolve: (name) => {
    const mod = (pages as Record<string, any>)[`./Pages/${name}.vue`]
    if (!mod) throw new Error(`Page not found: ${name}`)
    return mod
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) }).use(plugin)
    app.mount(el)
  },
  progress: { color: '#16a34a' },
})
