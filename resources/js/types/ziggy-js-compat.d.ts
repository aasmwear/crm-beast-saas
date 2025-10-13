declare module 'ziggy-js' {
  export type RouteFn = (name?: string, params?: any, absolute?: boolean, config?: any) => any
  const route: RouteFn
  export { route }
  export default route
}
