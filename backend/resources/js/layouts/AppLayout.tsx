import { PropsWithChildren, useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import BottomNav from '@/components/BottomNav';
import FlashMessage from '@/components/FlashMessage';
import InstallPrompt from '@/components/InstallPrompt';
import ThemeToggle from '@/components/ThemeToggle';
import { ScreenReaderAnnouncer, SkipToContent } from '@/components/Accessibility';
import { I18nProvider, useTranslation } from '@/utils/i18n';
import LanguageSelector from '@/components/LanguageSelector';
import NotificationCenter from '@/components/NotificationCenter';
import ShortcutsModal from '@/components/ShortcutsModal';
import { useKeyboardShortcuts } from '@/hooks/useKeyboardShortcuts';
import { useFocusTrap } from '@/hooks/useAccessibility';

interface LayoutProps {
    title?: string;
}

export default function AppLayout({ title, children }: PropsWithChildren<LayoutProps>) {
    return (
        <I18nProvider>
            <AppLayoutInner title={title}>{children}</AppLayoutInner>
        </I18nProvider>
    );
}

function AppLayoutInner({ title, children }: PropsWithChildren<LayoutProps>) {
    const { auth, flash } = usePage<PageProps>().props;
    const { t, dir } = useTranslation();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    useKeyboardShortcuts();
    const sidebarRef = useFocusTrap<HTMLDivElement>(sidebarOpen);

    // Close sidebar on Escape
    useEffect(() => {
        if (!sidebarOpen) return;
        const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') setSidebarOpen(false); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [sidebarOpen]);

    return (
        <>
            <Head title={title} />
            <SkipToContent />
            <ScreenReaderAnnouncer />
            <ShortcutsModal />

            <div className="min-h-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
                {/* Header */}
                <header className="sticky top-0 z-40 bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-gray-800" role="banner">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-14 items-center justify-between">
                            {/* Left: Logo + Nav */}
                            <div className="flex items-center gap-6">
                                <button
                                    type="button"
                                    aria-label={sidebarOpen ? 'Close navigation menu' : 'Open navigation menu'}
                                    aria-expanded={sidebarOpen}
                                    className="lg:hidden -m-2 p-2 min-w-[44px] min-h-[44px] text-gray-500"
                                    onClick={() => setSidebarOpen(!sidebarOpen)}
                                >
                                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                    </svg>
                                </button>

                                <Link href="/" className="flex items-center gap-2">
                                    <span className="text-xl">📖</span>
                                    <span className="font-bold text-lg text-indigo-600 dark:text-indigo-400">
                                        HisWord
                                    </span>
                                </Link>

                                <nav className="hidden lg:flex items-center gap-1" aria-label="Main navigation">
                                    <NavLink href="/" active={route().current('home')}>{t('nav.home')}</NavLink>
                                    <NavLink href="/read" active={route().current('reader')}>{t('nav.read')}</NavLink>
                                    <NavLink href="/search" active={route().current('search')}>{t('nav.search')}</NavLink>
                                    <NavLink href="/modules" active={route().current('modules')}>Modules</NavLink>
                                    <NavLink href="/bookmarks" active={route().current('bookmarks')}>{t('nav.bookmarks')}</NavLink>
                                    <NavLink href="/notes" active={route().current('notes')}>{t('nav.notes')}</NavLink>
                                    <NavLink href="/plans" active={route().current('plans')}>{t('nav.plans')}</NavLink>
                                </nav>
                            </div>

                            {/* Right: Theme + User */}
                            <div className="flex items-center gap-3">
                                <LanguageSelector compact />
                                <ThemeToggle />
                                {auth.user && <NotificationCenter />}

                                {auth.user ? (
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm text-gray-600 dark:text-gray-400 hidden sm:block">
                                            {auth.user.name}
                                        </span>
                                        <Link
                                            href="/profile"
                                            className="rounded-full bg-indigo-100 dark:bg-indigo-900 p-1.5 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-800 transition-colors"
                                        >
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" />
                                            </svg>
                                        </Link>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="text-sm text-red-500 hover:text-red-600 transition-colors"
                                        >
                                            {t('auth.logout')}
                                        </Link>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2">
                                        <Link
                                            href="/login"
                                            className="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                        >
                                            {t('auth.login')}
                                        </Link>
                                        <Link
                                            href="/register"
                                            className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 transition-colors"
                                        >
                                            {t('auth.register')}
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                {/* Mobile sidebar */}
                {sidebarOpen && (
                    <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
                        <div className="fixed inset-0 bg-black/30" onClick={() => setSidebarOpen(false)} aria-hidden="true" />
                        <div ref={sidebarRef} className="fixed inset-y-0 left-0 w-64 bg-white dark:bg-gray-900 p-6 shadow-xl">
                            <div className="flex items-center justify-between mb-8">
                                <span className="font-bold text-lg text-indigo-600 dark:text-indigo-400">
                                    HisWord
                                </span>
                                <button onClick={() => setSidebarOpen(false)} className="text-gray-500">
                                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <nav className="flex flex-col gap-1" aria-label="Mobile navigation">
                                <MobileNavLink href="/" onClick={() => setSidebarOpen(false)}>{t('nav.home')}</MobileNavLink>
                                <MobileNavLink href="/read" onClick={() => setSidebarOpen(false)}>{t('nav.read')}</MobileNavLink>
                                <MobileNavLink href="/search" onClick={() => setSidebarOpen(false)}>{t('nav.search')}</MobileNavLink>
                                <MobileNavLink href="/modules" onClick={() => setSidebarOpen(false)}>Modules</MobileNavLink>
                                <MobileNavLink href="/bookmarks" onClick={() => setSidebarOpen(false)}>{t('nav.bookmarks')}</MobileNavLink>
                                <MobileNavLink href="/notes" onClick={() => setSidebarOpen(false)}>{t('nav.notes')}</MobileNavLink>
                                <MobileNavLink href="/plans" onClick={() => setSidebarOpen(false)}>{t('nav.plans')}</MobileNavLink>
                                <MobileNavLink href="/settings" onClick={() => setSidebarOpen(false)}>{t('nav.settings')}</MobileNavLink>
                            </nav>
                        </div>
                    </div>
                )}

                {/* Flash Messages */}
                <FlashMessage flash={flash} />

                {/* Main content */}
                <main id="main-content" className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                    {children}
                </main>

                {/* PWA Install Prompt */}
                <InstallPrompt />

                {/* Mobile Bottom Navigation */}
                <BottomNav />
            </div>
        </>
    );
}

function NavLink({ href, active, children }: { href: string; active?: boolean; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={`
                rounded-lg px-3 py-1.5 text-sm font-medium transition-colors
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2
                ${active
                    ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100'
                }
            `}
        >
            {children}
        </Link>
    );
}

function MobileNavLink({ href, onClick, children }: { href: string; onClick: () => void; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        >
            {children}
        </Link>
    );
}
