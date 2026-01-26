import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from 'ziggy'

const pages = import.meta.glob('./Pages/**/*.vue')

createInertiaApp({
  resolve: name => pages[`./Pages/${name}.vue`](),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })
    app.use(plugin)
    app.use(ZiggyVue, window.Ziggy)   // <-- important
    app.mount(el)
  },
})
