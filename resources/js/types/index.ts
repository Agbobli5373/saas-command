export type * from './auth';
export type * from './navigation';
export type * from './ui';

import type { Auth } from './auth';

export type SupportedLocale = {
    code: string;
    label: string;
};

export type SharedData = {
    name: string;
    locale: string;
    supportedLocales: SupportedLocale[];
    translations: Record<string, string>;
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
};
