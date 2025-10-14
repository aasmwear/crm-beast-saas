import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.ts'],
      refresh: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      // app alias
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
      // 👇 force *all* `@ziggy` imports to use the vendor ESM build
      '@ziggy': fileURLToPath(new URL('./vendor/tightenco/ziggy/dist/index.esm.js', import.meta.url)),
    },
  },
  server: {
    host: true,
    port: Number(process.env.VITE_PORT) || 5199,
    hmr: {
      host: 'localhost',
      port: Number(process.env.VITE_PORT) || 5199,
    },
  },
})
