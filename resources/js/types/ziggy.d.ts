import type routeFn from '@ziggy'
declare module 'vue' {
  interface ComponentCustomProperties {
    route: typeof routeFn
  }
}
