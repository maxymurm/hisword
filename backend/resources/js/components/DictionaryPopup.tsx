import { useEffect, useState } from 'react';

interface DictionaryPopupProps {
    moduleKey: string;
    lookupKey: string;
    position: { top: number; left: number };
    onClose: () => void;
    availableModules: { id: string; key: string; name: string }[];
}

export default function DictionaryPopup({
    moduleKey: initialModuleKey,
    lookupKey,
    position,
    onClose,
    availableModules,
}: DictionaryPopupProps) {
    const [selectedModule, setSelectedModule] = useState(initialModuleKey);
    const [entry, setEntry] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!selectedModule || !lookupKey) return;

        setLoading(true);
        setError(null);

        fetch(`/dictionary/${selectedModule}/${encodeURIComponent(lookupKey)}`)
            .then(res => res.json())
            .then(data => {
                setEntry(data.entry);
                if (!data.entry) setError('No entry found');
            })
            .catch(() => setError('Failed to load entry'))
            .finally(() => setLoading(false));
    }, [selectedModule, lookupKey]);

    // Close on Escape
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    // Constrain position to viewport
    const style: React.CSSProperties = {
        position: 'fixed',
        top: Math.min(position.top, window.innerHeight - 320),
        left: Math.max(16, Math.min(position.left, window.innerWidth - 380)),
        zIndex: 60,
    };

    return (
        <>
            {/* Backdrop */}
            <div className="fixed inset-0 z-50" onClick={onClose} />

            {/* Popup */}
            <div style={style} className="z-60 w-[360px] max-h-[300px] flex flex-col rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden animate-in fade-in slide-in-from-top-2">
                {/* Header */}
                <div className="flex-none flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                    <div className="flex items-center gap-2">
                        <svg className="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span className="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {lookupKey}
                        </span>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Module selector (if multiple) */}
                {availableModules.length > 1 && (
                    <div className="flex-none px-4 py-2 border-b border-gray-100 dark:border-gray-800">
                        <select
                            value={selectedModule}
                            onChange={(e) => setSelectedModule(e.target.value)}
                            className="w-full text-xs rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-2 py-1 focus:ring-1 focus:ring-indigo-500"
                        >
                            {availableModules.map(mod => (
                                <option key={mod.key} value={mod.key}>{mod.name}</option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Content */}
                <div className="flex-1 overflow-y-auto px-4 py-3">
                    {loading && (
                        <div className="flex items-center justify-center py-8">
                            <div className="h-5 w-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                        </div>
                    )}

                    {error && !loading && (
                        <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-4">{error}</p>
                    )}

                    {!loading && entry && (
                        <div
                            className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed prose prose-sm dark:prose-invert max-w-none"
                            dangerouslySetInnerHTML={{ __html: entry }}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
