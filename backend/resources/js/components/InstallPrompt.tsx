import { useEffect, useState } from 'react';

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

export default function InstallPrompt() {
    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [dismissed, setDismissed] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('pwa_install_dismissed') === 'true';
        }
        return false;
    });

    useEffect(() => {
        const handler = (e: Event) => {
            e.preventDefault();
            setDeferredPrompt(e as BeforeInstallPromptEvent);
        };
        window.addEventListener('beforeinstallprompt', handler);
        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    if (!deferredPrompt || dismissed) return null;

    const install = async () => {
        await deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            setDeferredPrompt(null);
        }
    };

    const dismiss = () => {
        setDismissed(true);
        localStorage.setItem('pwa_install_dismissed', 'true');
    };

    return (
        <div className="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-96 z-50 animate-in slide-in-from-bottom rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl p-4">
            <div className="flex items-start gap-3">
                <span className="text-2xl flex-none">📖</span>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-900 dark:text-white">
                        Install HisWord
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Add to your home screen for quick access and offline reading
                    </p>
                    <div className="flex gap-2 mt-3">
                        <button
                            onClick={install}
                            className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 transition-colors"
                        >
                            Install
                        </button>
                        <button
                            onClick={dismiss}
                            className="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                            Not now
                        </button>
                    </div>
                </div>
                <button
                    onClick={dismiss}
                    className="flex-none text-gray-400 hover:text-gray-500 transition-colors"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
