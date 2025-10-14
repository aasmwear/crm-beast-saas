import './bootstrap';
import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import route from '@ziggy'; // ← vendor ESM via alias
// Tiny Vue plugin to expose `route()` on all components
const ZiggyVueLite = {
    install(app) {
        app.config.globalProperties.route = (name, params, absolute, config) => route(name, params, absolute, config ?? window.Ziggy);
    },
};
// Tell TS exactly what the glob returns
const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
createInertiaApp({
    resolve: (name) => {
        const mod = pages[`./Pages/${name}.vue`];
        if (!mod)
            throw new Error(`Page not found: ${name}`);
        return mod;
    },
    setup({ el, App, props, plugin, }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVueLite);
        app.mount(el);
    },
    progress: { color: '#16a34a' },
});
