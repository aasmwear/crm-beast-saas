export {}

declare global {
  // minimal typing – good enough for TS to stop complaining in .vue files
  function route(name?: string, params?: any, absolute?: boolean, config?: any): any
}
