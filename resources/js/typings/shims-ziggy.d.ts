// Let TypeScript accept Ziggy's global helpers without complaining
declare function route(
  name?: string,
  params?: any,
  absolute?: boolean,
  config?: any
): any;

declare const Ziggy: any;
