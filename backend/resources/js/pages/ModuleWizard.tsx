import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { router } from '@inertiajs/react';

interface Language { code: string; count: number }
interface ModuleType { name: string; count: number }
interface ModuleItem { key: string; name: string; description: string; type: string; language: string; size: number | null }

interface Props {
    languages: Language[];
    types: ModuleType[];
}

type Step = 'language' | 'type' | 'select' | 'confirm';

export default function ModuleWizard({ languages, types }: Props) {
    const [step, setStep] = useState<Step>('language');
    const [selectedLanguage, setSelectedLanguage] = useState('');
    const [selectedType, setSelectedType] = useState('');
    const [modules, setModules] = useState<ModuleItem[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [loading, setLoading] = useState(false);
    const [installing, setInstalling] = useState<string | null>(null);
    const [languageSearch, setLanguageSearch] = useState('');

    const steps: { key: Step; label: string }[] = [
        { key: 'language', label: 'Language' },
        { key: 'type', label: 'Category' },
        { key: 'select', label: 'Select' },
        { key: 'confirm', label: 'Install' },
    ];
    const currentIndex = steps.findIndex(s => s.key === step);

    const filteredLanguages = languages.filter(l =>
        l.code.toLowerCase().includes(languageSearch.toLowerCase())
    );

    function pickLanguage(code: string) {
        setSelectedLanguage(code);
        setStep('type');
    }

    function pickType(name: string) {
        setSelectedType(name);
        setLoading(true);
        fetch(`/module-wizard/modules?language=${encodeURIComponent(selectedLanguage)}&type=${encodeURIComponent(name)}`)
            .then(r => r.json())
            .then((data: ModuleItem[]) => { setModules(data); setLoading(false); setStep('select'); })
            .catch(() => setLoading(false));
    }

    function toggleModule(key: string) {
        setSelected(prev => {
            const next = new Set(prev);
            next.has(key) ? next.delete(key) : next.add(key);
            return next;
        });
    }

    function installSelected() {
        const keys = Array.from(selected);
        if (keys.length === 0) return;

        let idx = 0;
        function installNext() {
            if (idx >= keys.length) {
                setInstalling(null);
                router.visit('/modules');
                return;
            }
            const key = keys[idx];
            setInstalling(key);
            fetch(`/modules/${encodeURIComponent(key)}/install`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' } })
                .then(() => { idx++; installNext(); })
                .catch(() => { idx++; installNext(); });
        }
        installNext();
    }

    function goBack() {
        if (step === 'type') setStep('language');
        else if (step === 'select') setStep('type');
        else if (step === 'confirm') setStep('select');
    }

    const selectedModules = modules.filter(m => selected.has(m.key));

    return (
        <AppLayout title="Module Wizard">
            <div className="max-w-3xl mx-auto px-4 py-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Install Modules</h1>
                <p className="text-gray-500 dark:text-gray-400 mb-8">Follow the steps to find and install Bible modules.</p>

                {/* Step indicator */}
                <div className="flex items-center mb-8">
                    {steps.map((s, i) => (
                        <div key={s.key} className="flex items-center flex-1">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold ${i <= currentIndex ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'}`}>
                                {i + 1}
                            </div>
                            <span className={`ml-2 text-sm ${i <= currentIndex ? 'text-indigo-600 dark:text-indigo-400 font-medium' : 'text-gray-400 dark:text-gray-500'}`}>{s.label}</span>
                            {i < steps.length - 1 && <div className={`flex-1 h-0.5 mx-3 ${i < currentIndex ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'}`} />}
                        </div>
                    ))}
                </div>

                {/* Back button */}
                {step !== 'language' && !installing && (
                    <button onClick={goBack} className="mb-4 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Back</button>
                )}

                {/* Step 1: Language */}
                {step === 'language' && (
                    <div>
                        <input
                            type="text"
                            placeholder="Search languages..."
                            value={languageSearch}
                            onChange={e => setLanguageSearch(e.target.value)}
                            className="w-full mb-4 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                        />
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            {filteredLanguages.map(l => (
                                <button
                                    key={l.code}
                                    onClick={() => pickLanguage(l.code)}
                                    className="p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 bg-white dark:bg-gray-800 text-left transition"
                                >
                                    <div className="font-medium text-gray-900 dark:text-white">{l.code}</div>
                                    <div className="text-sm text-gray-500 dark:text-gray-400">{l.count} modules</div>
                                </button>
                            ))}
                        </div>
                        {filteredLanguages.length === 0 && (
                            <p className="text-center text-gray-500 dark:text-gray-400 py-8">No languages match your search.</p>
                        )}
                    </div>
                )}

                {/* Step 2: Category */}
                {step === 'type' && (
                    <div className="grid grid-cols-2 gap-3">
                        {types.map(t => (
                            <button
                                key={t.name}
                                onClick={() => pickType(t.name)}
                                className="p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 bg-white dark:bg-gray-800 text-left transition"
                            >
                                <div className="font-medium text-gray-900 dark:text-white capitalize">{t.name}</div>
                                <div className="text-sm text-gray-500 dark:text-gray-400">{t.count} available</div>
                            </button>
                        ))}
                    </div>
                )}

                {/* Step 3: Select modules */}
                {step === 'select' && (
                    <div>
                        {loading ? (
                            <p className="text-center text-gray-500 py-8">Loading modules...</p>
                        ) : (
                            <>
                                <div className="space-y-2 max-h-96 overflow-y-auto">
                                    {modules.map(m => (
                                        <label key={m.key} className="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 cursor-pointer hover:border-indigo-500 transition">
                                            <input
                                                type="checkbox"
                                                checked={selected.has(m.key)}
                                                onChange={() => toggleModule(m.key)}
                                                className="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium text-gray-900 dark:text-white">{m.name}</div>
                                                <div className="text-sm text-gray-500 dark:text-gray-400 truncate">{m.description || m.key}</div>
                                                {m.size && <div className="text-xs text-gray-400 mt-1">{(m.size / 1024 / 1024).toFixed(1)} MB</div>}
                                            </div>
                                        </label>
                                    ))}
                                </div>
                                {modules.length === 0 && (
                                    <p className="text-center text-gray-500 dark:text-gray-400 py-8">No modules found for this selection.</p>
                                )}
                                {selected.size > 0 && (
                                    <button onClick={() => setStep('confirm')} className="mt-4 w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                                        Continue with {selected.size} module{selected.size > 1 ? 's' : ''}
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                )}

                {/* Step 4: Confirm & install */}
                {step === 'confirm' && (
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ready to install</h2>
                        <div className="space-y-2 mb-6">
                            {selectedModules.map(m => (
                                <div key={m.key} className="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                    {installing === m.key ? (
                                        <div className="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin" />
                                    ) : installing && !selected.has(m.key) ? (
                                        <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                    ) : (
                                        <div className="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600" />
                                    )}
                                    <span className="text-gray-900 dark:text-white">{m.name}</span>
                                    <span className="text-sm text-gray-500 dark:text-gray-400 ml-auto">{m.key}</span>
                                </div>
                            ))}
                        </div>
                        {!installing && (
                            <button onClick={installSelected} className="w-full py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold text-lg">
                                Install {selected.size} Module{selected.size > 1 ? 's' : ''}
                            </button>
                        )}
                        {installing && (
                            <p className="text-center text-indigo-600 dark:text-indigo-400 font-medium">Installing modules...</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
