import React, { createContext, useContext, useCallback, useMemo, ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

// ─── Types ───────────────────────────────────────────────────────────────────

type NestedTranslations = {
    [key: string]: string | NestedTranslations;
};

interface LocaleContext {
    locale: string;
    dir: 'ltr' | 'rtl';
    availableLocales: string[];
    t: (key: string, replacements?: Record<string, string | number>) => string;
    tc: (key: string, count: number, replacements?: Record<string, string | number>) => string;
    formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;
    formatDate: (date: Date | string, options?: Intl.DateTimeFormatOptions) => string;
    formatRelativeTime: (date: Date | string) => string;
    isRtl: boolean;
}

interface PageProps {
    locale?: string;
    dir?: 'ltr' | 'rtl';
    availableLocales?: string[];
    translations?: NestedTranslations;
    [key: string]: unknown;
}

// ─── Translation cache ──────────────────────────────────────────────────────

const translationCache = new Map<string, NestedTranslations>();

async function loadTranslations(locale: string): Promise<NestedTranslations> {
    if (translationCache.has(locale)) {
        return translationCache.get(locale)!;
    }

    try {
        const response = await fetch(`/lang/${locale}.json`);
        if (!response.ok) {
            console.warn(`Failed to load translations for ${locale}, falling back to en`);
            return loadTranslations('en');
        }
        const translations = await response.json();
        translationCache.set(locale, translations);
        return translations;
    } catch {
        console.warn(`Error loading translations for ${locale}`);
        return {};
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getNestedValue(obj: NestedTranslations, path: string): string | undefined {
    const keys = path.split('.');
    let current: NestedTranslations | string = obj;

    for (const key of keys) {
        if (typeof current !== 'object' || current === null) return undefined;
        current = (current as NestedTranslations)[key];
        if (current === undefined) return undefined;
    }

    return typeof current === 'string' ? current : undefined;
}

function interpolate(template: string, replacements: Record<string, string | number>): string {
    return template.replace(/\{(\w+)\}/g, (match, key) => {
        return key in replacements ? String(replacements[key]) : match;
    });
}

function pluralize(template: string, count: number): string {
    const parts = template.split('|').map(s => s.trim());
    if (parts.length === 1) return parts[0];
    // Simple: singular | plural
    return count === 1 ? parts[0] : parts[parts.length - 1];
}

// ─── Relative time units ────────────────────────────────────────────────────

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year',   60 * 60 * 24 * 365],
    ['month',  60 * 60 * 24 * 30],
    ['week',   60 * 60 * 24 * 7],
    ['day',    60 * 60 * 24],
    ['hour',   60 * 60],
    ['minute', 60],
    ['second', 1],
];

// ─── Context ─────────────────────────────────────────────────────────────────

const I18nContext = createContext<LocaleContext | null>(null);

// ─── Provider ────────────────────────────────────────────────────────────────

interface I18nProviderProps {
    children: ReactNode;
    translations?: NestedTranslations;
}

export function I18nProvider({ children, translations: propTranslations }: I18nProviderProps) {
    const page = usePage<PageProps>();
    const locale = page.props.locale ?? 'en';
    const dir = page.props.dir ?? 'ltr';
    const availableLocales = page.props.availableLocales ?? ['en'];
    const translations = propTranslations ?? page.props.translations ?? {};

    const t = useCallback((key: string, replacements?: Record<string, string | number>): string => {
        const value = getNestedValue(translations, key);
        if (value === undefined) return key;
        return replacements ? interpolate(value, replacements) : value;
    }, [translations]);

    const tc = useCallback((key: string, count: number, replacements?: Record<string, string | number>): string => {
        const value = getNestedValue(translations, key);
        if (value === undefined) return key;
        const pluralized = pluralize(value, count);
        const merged = { count, ...replacements };
        return interpolate(pluralized, merged);
    }, [translations]);

    const formatNumber = useCallback((value: number, options?: Intl.NumberFormatOptions): string => {
        try {
            return new Intl.NumberFormat(locale, options).format(value);
        } catch {
            return String(value);
        }
    }, [locale]);

    const formatDate = useCallback((date: Date | string, options?: Intl.DateTimeFormatOptions): string => {
        const d = typeof date === 'string' ? new Date(date) : date;
        try {
            return new Intl.DateTimeFormat(locale, options ?? {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }).format(d);
        } catch {
            return d.toLocaleDateString();
        }
    }, [locale]);

    const formatRelativeTime = useCallback((date: Date | string): string => {
        const d = typeof date === 'string' ? new Date(date) : date;
        const seconds = Math.round((d.getTime() - Date.now()) / 1000);

        try {
            const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
            for (const [unit, threshold] of RELATIVE_UNITS) {
                if (Math.abs(seconds) >= threshold) {
                    const value = Math.round(seconds / threshold);
                    return rtf.format(value, unit);
                }
            }
            return rtf.format(0, 'second');
        } catch {
            return d.toLocaleDateString();
        }
    }, [locale]);

    const value = useMemo<LocaleContext>(() => ({
        locale,
        dir,
        availableLocales,
        t,
        tc,
        formatNumber,
        formatDate,
        formatRelativeTime,
        isRtl: dir === 'rtl',
    }), [locale, dir, availableLocales, t, tc, formatNumber, formatDate, formatRelativeTime]);

    return React.createElement(
        I18nContext.Provider,
        { value },
        children
    );
}

// ─── Hook ────────────────────────────────────────────────────────────────────

export function useTranslation() {
    const context = useContext(I18nContext);
    if (!context) {
        throw new Error('useTranslation must be used within an I18nProvider');
    }
    return context;
}

// ─── Standalone t() for non-component use ────────────────────────────────────

let _fallbackTranslations: NestedTranslations = {};

export function setFallbackTranslations(translations: NestedTranslations) {
    _fallbackTranslations = translations;
}

export function t(key: string, replacements?: Record<string, string | number>): string {
    const value = getNestedValue(_fallbackTranslations, key);
    if (value === undefined) return key;
    return replacements ? interpolate(value, replacements) : value;
}

// ─── Locale utilities ────────────────────────────────────────────────────────

export const LOCALE_NAMES: Record<string, string> = {
    en: 'English',
    ar: 'العربية',
    cs: 'Čeština',
    de: 'Deutsch',
    es: 'Español',
    fr: 'Français',
    it: 'Italiano',
    ja: '日本語',
    ko: '한국어',
    nl: 'Nederlands',
    pt: 'Português',
    ru: 'Русский',
    sv: 'Svenska',
    th: 'ไทย',
    uk: 'Українська',
    'zh-Hans': '简体中文',
    'zh-Hant': '繁體中文',
};

export const RTL_LOCALES = new Set(['ar']);

export function isRtlLocale(locale: string): boolean {
    return RTL_LOCALES.has(locale);
}

export function getLocaleDirection(locale: string): 'ltr' | 'rtl' {
    return isRtlLocale(locale) ? 'rtl' : 'ltr';
}

export function getLocaleName(locale: string): string {
    return LOCALE_NAMES[locale] ?? locale;
}

export function getBrowserLocale(): string {
    const nav = typeof navigator !== 'undefined' ? navigator : null;
    if (!nav) return 'en';

    const lang = nav.language || (nav.languages?.[0]);
    if (!lang) return 'en';

    // Exact match
    if (LOCALE_NAMES[lang]) return lang;

    // Language code match (e.g., "en-US" → "en")
    const base = lang.split('-')[0];
    if (LOCALE_NAMES[base]) return base;

    // Chinese variants
    if (base === 'zh') {
        if (lang.includes('Hant') || lang.includes('TW') || lang.includes('HK')) {
            return 'zh-Hant';
        }
        return 'zh-Hans';
    }

    return 'en';
}

export { loadTranslations };
