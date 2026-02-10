import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { SharedData } from '@/types';

type ReplacementValue = string | number;

function interpolate(template: string, replacements: Record<string, ReplacementValue>): string {
    return template.replace(/:([a-zA-Z0-9_]+)/g, (match, token: string) => {
        const replacement = replacements[token];

        if (replacement === undefined) {
            return match;
        }

        return String(replacement);
    });
}

function normalizeDate(value: string | number | Date): Date {
    if (value instanceof Date) {
        return value;
    }

    return new Date(value);
}

export function useI18n() {
    const {
        locale,
        translations,
        supportedLocales,
    } = usePage<SharedData>().props;

    const translate = useCallback(
        (key: string, replacements: Record<string, ReplacementValue> = {}): string => {
            const template = translations[key] ?? key;

            return interpolate(template, replacements);
        },
        [translations],
    );

    const formatDate = useCallback(
        (value: string | number | Date, options?: Intl.DateTimeFormatOptions): string => {
            return new Intl.DateTimeFormat(locale, options).format(normalizeDate(value));
        },
        [locale],
    );

    const formatDateTime = useCallback(
        (value: string | number | Date, options?: Intl.DateTimeFormatOptions): string => {
            return new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeStyle: 'short',
                ...options,
            }).format(normalizeDate(value));
        },
        [locale],
    );

    return {
        t: translate,
        locale,
        supportedLocales,
        formatDate,
        formatDateTime,
    } as const;
}
