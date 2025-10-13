import { route as ziggyRoute } from 'ziggy-js'

declare module 'vue' {
  interface ComponentCustomProperties {
    route: typeof ziggyRoute
  }
}
export {}
