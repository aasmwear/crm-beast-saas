// vite.config.js (ESM-safe)
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// __dirname in ESM
const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.ts'], refresh: true }),
    vue(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
      // match your vendor dir contents
      ziggy: path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/index.esm.js'),
    },
  },
  server: {
    host: true,
    port: Number(process.env.VITE_PORT) || 5199,
    hmr: {
      host: 'localhost',
      port: Number(process.env.VITE_PORT) || 5199,
      clientPort: Number(process.env.VITE_PORT) || 5199,
    },
  },
})
