import './bootstrap'
import '../css/app.css'

import { createApp, h, type DefineComponent, type Plugin as VuePlugin } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import route from '@ziggy' // ← vendor ESM via alias

// Tiny Vue plugin to expose `route()` on all components
const ZiggyVueLite: VuePlugin = {
  install(app) {
    app.config.globalProperties.route = (
      name?: string,
      params?: any,
      absolute?: boolean,
      config?: any
    ) => route(name as any, params, absolute, config ?? (window as any).Ziggy)
  },
}

// Tell TS exactly what the glob returns
const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue', { eager: true })

createInertiaApp({
  resolve: (name: string) => {
    const mod = pages[`./Pages/${name}.vue`]
    if (!mod) throw new Error(`Page not found: ${name}`)
    return mod
  },
  setup({
    el,
    App,
    props,
    plugin,
  }: {
    el: Element
    App: DefineComponent
    props: Record<string, unknown>
    plugin: VuePlugin
  }) {
    const app = createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVueLite)

    app.mount(el)
  },
  progress: { color: '#16a34a' },
})
