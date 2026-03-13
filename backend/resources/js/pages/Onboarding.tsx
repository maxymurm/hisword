import { useCallback, useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';

interface BibleModule {
    id: string;
    key: string;
    name: string;
    language: string;
    description: string | null;
}

interface OnboardingProps {
    bibleModules: BibleModule[];
}

const TOTAL_STEPS = 7;

const FEATURE_SLIDES = [
    {
        icon: '📖',
        title: 'Read & Study',
        description: 'Access multiple Bible translations with commentary, cross-references, and dictionary lookups — all in one place.',
    },
    {
        icon: '🎨',
        title: 'Annotate & Organize',
        description: 'Highlight verses in custom colors, add personal notes, create bookmark folders, and pin your favorites for quick access.',
    },
    {
        icon: '🔄',
        title: 'Sync Everywhere',
        description: 'Your reading progress, annotations, and preferences sync seamlessly across all your devices — web, Android, and iOS.',
    },
];

export default function Onboarding({ bibleModules }: OnboardingProps) {
    const [step, setStep] = useState(0);
    const [selectedModule, setSelectedModule] = useState('KJV');
    const [theme, setTheme] = useState<'light' | 'dark' | 'system'>('system');
    const [notificationsEnabled, setNotificationsEnabled] = useState(false);
    const [direction, setDirection] = useState<'forward' | 'backward'>('forward');
    const [isAnimating, setIsAnimating] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    // Check if already completed
    useEffect(() => {
        const completed = localStorage.getItem('onboarding_completed');
        if (completed === 'true') {
            router.visit('/');
        }
    }, []);

    const goTo = useCallback((nextStep: number) => {
        if (isAnimating || nextStep === step) return;
        setDirection(nextStep > step ? 'forward' : 'backward');
        setIsAnimating(true);
        setTimeout(() => {
            setStep(nextStep);
            setIsAnimating(false);
        }, 300);
    }, [step, isAnimating]);

    const next = useCallback(() => {
        if (step < TOTAL_STEPS - 1) goTo(step + 1);
    }, [step, goTo]);

    const prev = useCallback(() => {
        if (step > 0) goTo(step - 1);
    }, [step, goTo]);

    const skip = useCallback(() => {
        goTo(TOTAL_STEPS - 1);
    }, [goTo]);

    const complete = useCallback(async () => {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

        try {
            await fetch('/onboarding/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    preferred_module: selectedModule,
                    theme,
                    notifications_enabled: notificationsEnabled,
                }),
            });
        } catch {
            // Continue even if save fails
        }

        localStorage.setItem('onboarding_completed', 'true');
        router.visit('/');
    }, [selectedModule, theme, notificationsEnabled]);

    // Apply theme preview
    useEffect(() => {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else if (theme === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', prefersDark);
        }
    }, [theme]);

    // Keyboard navigation
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'ArrowRight' || e.key === 'Enter') next();
            else if (e.key === 'ArrowLeft') prev();
            else if (e.key === 'Escape') skip();
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [next, prev, skip]);

    const slideClass = isAnimating
        ? direction === 'forward'
            ? 'translate-x-full opacity-0'
            : '-translate-x-full opacity-0'
        : 'translate-x-0 opacity-100';

    return (
        <>
            <Head title="Welcome to HisWord" />
            <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-gray-950 dark:via-gray-900 dark:to-indigo-950 flex flex-col">
                {/* Skip button */}
                {step < TOTAL_STEPS - 1 && (
                    <div className="absolute top-4 right-4 z-10">
                        <button
                            onClick={skip}
                            className="text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            Skip
                        </button>
                    </div>
                )}

                {/* Main content */}
                <div className="flex-1 flex items-center justify-center p-6">
                    <div
                        ref={containerRef}
                        className={`w-full max-w-lg transition-all duration-300 ease-in-out transform ${slideClass}`}
                    >
                        {step === 0 && <WelcomeStep />}
                        {step >= 1 && step <= 3 && <FeatureStep slide={FEATURE_SLIDES[step - 1]} index={step - 1} />}
                        {step === 4 && (
                            <ModuleSelectStep
                                modules={bibleModules}
                                selected={selectedModule}
                                onSelect={setSelectedModule}
                            />
                        )}
                        {step === 5 && (
                            <ThemeStep theme={theme} onSelect={setTheme} />
                        )}
                        {step === 6 && <ReadyStep />}
                    </div>
                </div>

                {/* Bottom: Progress dots + navigation */}
                <div className="pb-8 px-6">
                    {/* Progress dots */}
                    <div className="flex items-center justify-center gap-2 mb-6">
                        {Array.from({ length: TOTAL_STEPS }).map((_, i) => (
                            <button
                                key={i}
                                onClick={() => goTo(i)}
                                className={`rounded-full transition-all duration-300 ${
                                    i === step
                                        ? 'w-8 h-2.5 bg-indigo-600 dark:bg-indigo-400'
                                        : i < step
                                            ? 'w-2.5 h-2.5 bg-indigo-300 dark:bg-indigo-600'
                                            : 'w-2.5 h-2.5 bg-gray-300 dark:bg-gray-700'
                                }`}
                                aria-label={`Go to step ${i + 1}`}
                            />
                        ))}
                    </div>

                    {/* Navigation buttons */}
                    <div className="flex items-center justify-between max-w-lg mx-auto">
                        <button
                            onClick={prev}
                            disabled={step === 0}
                            className="px-5 py-2.5 text-sm font-medium rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-0"
                        >
                            ← Back
                        </button>

                        {step < TOTAL_STEPS - 1 ? (
                            <button
                                onClick={next}
                                className="px-6 py-2.5 text-sm font-medium rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 shadow-lg shadow-indigo-200 dark:shadow-indigo-900/30 transition-all hover:scale-105"
                            >
                                Continue →
                            </button>
                        ) : (
                            <button
                                onClick={complete}
                                className="px-6 py-2.5 text-sm font-medium rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 shadow-lg shadow-indigo-200 dark:shadow-indigo-900/30 transition-all hover:scale-105"
                            >
                                Get Started 🚀
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

/* ─── Step Components ─── */

function WelcomeStep() {
    return (
        <div className="text-center space-y-6">
            <div className="text-7xl animate-bounce">📖</div>
            <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                Welcome to <span className="text-indigo-600 dark:text-indigo-400">HisWord</span>
            </h1>
            <p className="text-lg text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                Your personal Bible study companion. Read, annotate, and sync across all your devices.
            </p>
        </div>
    );
}

function FeatureStep({ slide, index }: { slide: typeof FEATURE_SLIDES[0]; index: number }) {
    const colors = [
        'from-blue-400 to-indigo-500',
        'from-amber-400 to-orange-500',
        'from-emerald-400 to-teal-500',
    ];

    return (
        <div className="text-center space-y-6">
            <div className={`inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-gradient-to-br ${colors[index]} shadow-xl`}>
                <span className="text-5xl">{slide.icon}</span>
            </div>
            <h2 className="text-3xl font-bold text-gray-900 dark:text-gray-100">{slide.title}</h2>
            <p className="text-lg text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
                {slide.description}
            </p>
        </div>
    );
}

function ModuleSelectStep({
    modules,
    selected,
    onSelect,
}: {
    modules: BibleModule[];
    selected: string;
    onSelect: (key: string) => void;
}) {
    const popularModules = ['KJV', 'ESV', 'NIV', 'NASB', 'NLT', 'CSB'];
    const popular = modules.filter(m => popularModules.includes(m.key));
    const others = modules.filter(m => !popularModules.includes(m.key));

    return (
        <div className="space-y-6">
            <div className="text-center">
                <div className="text-5xl mb-4">📚</div>
                <h2 className="text-3xl font-bold text-gray-900 dark:text-gray-100">Choose Your Bible</h2>
                <p className="mt-2 text-gray-600 dark:text-gray-400">
                    Select your primary translation. You can add more later.
                </p>
            </div>

            <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
                {popular.length > 0 && (
                    <>
                        <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-1">
                            Popular
                        </p>
                        {popular.map(m => (
                            <ModuleOption key={m.key} module={m} selected={selected === m.key} onSelect={onSelect} />
                        ))}
                    </>
                )}
                {others.length > 0 && (
                    <>
                        <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-1 mt-3">
                            All Translations
                        </p>
                        {others.map(m => (
                            <ModuleOption key={m.key} module={m} selected={selected === m.key} onSelect={onSelect} />
                        ))}
                    </>
                )}
                {modules.length === 0 && (
                    <div className="text-center py-8 text-gray-400">
                        <p>No modules installed yet.</p>
                        <p className="text-sm mt-1">You can install modules after setup.</p>
                    </div>
                )}
            </div>
        </div>
    );
}

function ModuleOption({
    module,
    selected,
    onSelect,
}: {
    module: BibleModule;
    selected: boolean;
    onSelect: (key: string) => void;
}) {
    return (
        <button
            onClick={() => onSelect(module.key)}
            className={`w-full text-left px-4 py-3 rounded-xl border-2 transition-all ${
                selected
                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950'
                    : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 bg-white dark:bg-gray-900'
            }`}
        >
            <div className="flex items-center justify-between">
                <div>
                    <span className="font-semibold text-gray-900 dark:text-gray-100">{module.key}</span>
                    <span className="ml-2 text-sm text-gray-500 dark:text-gray-400">{module.name}</span>
                </div>
                {selected && (
                    <span className="text-indigo-600 dark:text-indigo-400">
                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                    </span>
                )}
            </div>
            {module.language && (
                <span className="text-xs text-gray-400 dark:text-gray-500">{module.language}</span>
            )}
        </button>
    );
}

function ThemeStep({
    theme,
    onSelect,
}: {
    theme: 'light' | 'dark' | 'system';
    onSelect: (t: 'light' | 'dark' | 'system') => void;
}) {
    const themes = [
        { key: 'light' as const, icon: '☀️', label: 'Light', desc: 'Clean and bright' },
        { key: 'dark' as const, icon: '🌙', label: 'Dark', desc: 'Easy on the eyes' },
        { key: 'system' as const, icon: '💻', label: 'System', desc: 'Match your device' },
    ];

    return (
        <div className="space-y-6">
            <div className="text-center">
                <div className="text-5xl mb-4">🎨</div>
                <h2 className="text-3xl font-bold text-gray-900 dark:text-gray-100">Choose Your Theme</h2>
                <p className="mt-2 text-gray-600 dark:text-gray-400">
                    Pick how HisWord looks. You can change this anytime.
                </p>
            </div>

            <div className="grid grid-cols-3 gap-3">
                {themes.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => onSelect(t.key)}
                        className={`flex flex-col items-center p-4 rounded-xl border-2 transition-all ${
                            theme === t.key
                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950 shadow-md'
                                : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 bg-white dark:bg-gray-900'
                        }`}
                    >
                        <span className="text-3xl mb-2">{t.icon}</span>
                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">{t.label}</span>
                        <span className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t.desc}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}

function ReadyStep() {
    return (
        <div className="text-center space-y-6">
            <div className="text-7xl">🎉</div>
            <h2 className="text-3xl font-bold text-gray-900 dark:text-gray-100">You're All Set!</h2>
            <p className="text-lg text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                HisWord is ready for you. Start reading, studying, and growing in your faith.
            </p>
            <div className="flex flex-col items-center gap-3 pt-4">
                <div className="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <span>✅ Bible ready</span>
                    <span>✅ Theme set</span>
                    <span>✅ Synced</span>
                </div>
            </div>
        </div>
    );
}
