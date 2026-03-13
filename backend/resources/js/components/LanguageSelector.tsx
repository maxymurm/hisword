import { useTranslation, LOCALE_NAMES, getLocaleDirection } from '@/utils/i18n';
import { router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';

interface LanguageSelectorProps {
    className?: string;
    compact?: boolean;
}

export default function LanguageSelector({ className = '', compact = false }: LanguageSelectorProps) {
    const { locale, availableLocales, t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function switchLocale(newLocale: string) {
        setIsOpen(false);
        if (newLocale === locale) return;

        // Update the document direction for RTL support
        document.documentElement.dir = getLocaleDirection(newLocale);
        document.documentElement.lang = newLocale;

        // Reload the page with the new locale
        router.reload({
            data: { locale: newLocale },
            only: ['locale', 'dir', 'availableLocales', 'translations'],
        });
    }

    const currentName = LOCALE_NAMES[locale] ?? locale;

    return (
        <div ref={dropdownRef} className={`relative inline-block ${className}`}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-300 dark:hover:bg-gray-700 min-w-[44px] min-h-[44px]"
                aria-expanded={isOpen}
                aria-haspopup="listbox"
                aria-label={t('settings.language')}
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                {!compact && <span>{currentName}</span>}
                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isOpen ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'} />
                </svg>
            </button>

            {isOpen && (
                <div
                    className="absolute end-0 z-50 mt-1 max-h-64 w-48 overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    role="listbox"
                    aria-label={t('settings.language')}
                >
                    {availableLocales.map((loc) => {
                        const name = LOCALE_NAMES[loc] ?? loc;
                        const isActive = loc === locale;
                        return (
                            <button
                                key={loc}
                                type="button"
                                role="option"
                                aria-selected={isActive}
                                onClick={() => switchLocale(loc)}
                                className={`flex w-full items-center px-3 py-2 text-sm min-h-[44px] ${
                                    isActive
                                        ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'
                                }`}
                                dir={getLocaleDirection(loc)}
                            >
                                <span className="truncate">{name}</span>
                                {isActive && (
                                    <svg className="ms-auto h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
