import 'vue'

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    route: (name?: string, params?: any, absolute?: boolean, config?: any) => any
  }
}
