import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'

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
      '@': path.resolve(__dirname, 'resources/js'),

      // ✅ Force BOTH to known-good Composer vendor ESM builds (present in your repo)
      //  - route() helper:
      'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/index.esm.js'),
      //  - Vue plugin:
      'ziggy': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/vue.es.js'),
    },
  },
  optimizeDeps: {
    include: ['ziggy-js'],
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
