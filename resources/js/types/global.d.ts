/**
 * Global type definitions for CRM Beast.
 */

import type { AxiosInstance } from 'axios';
import ziggyRoute from 'ziggy-js';

declare global {
    interface Window {
        axios: AxiosInstance;
        Echo?: import('laravel-echo').default;
        Pusher?: typeof import('pusher-js');
        route: typeof ziggyRoute;
    }

    /* eslint-disable no-var */
    var route: typeof ziggyRoute;
    var Ziggy: { url?: string; port?: number | null; defaults?: Record<string, unknown>; routes?: Record<string, unknown> };
}

export {};
