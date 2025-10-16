declare module '@ziggy' {
  // Matches Ziggy ESM signature closely enough for TS in our app
  export default function route(
    name?: string,
    params?: any,
    absolute?: boolean,
    config?: any
  ): any
}
