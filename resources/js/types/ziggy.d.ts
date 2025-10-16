declare module '@ziggy' {
  import type { Plugin } from 'vue'

  // Matches vendor/tightenco/ziggy/dist/index.esm.js (named exports)
  export function route(
    name?: string,
    params?: Record<string, unknown> | unknown[],
    absolute?: boolean,
    config?: unknown
  ): any

  export const ZiggyVue: Plugin
}
