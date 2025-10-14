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

      // ✅ Force Ziggy to ESM build (has default export)
      // This completely overrides node_modules/ziggy-js resolution.
      'ziggy-js': path.resolve(
        __dirname,
        'vendor/tightenco/ziggy/dist/index.esm.js'
      ),
    },
  },
  server: {
    host: true,
    port: Number(process.env.VITE_PORT) || 5201,
    hmr: {
      host: 'localhost',
      port: Number(process.env.VITE_PORT) || 5201,
    },
  },
})
