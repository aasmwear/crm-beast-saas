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

      // ✅ Force Ziggy to ESM builds (fixes “default not exported” in prod build)
      'ziggy-js': 'ziggy-js/dist/index.m.js',
      'ziggy': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/index.esm.js'),
    },
  },
  optimizeDeps: {
    // helps Vite pre-bundle Ziggy properly in dev too
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
