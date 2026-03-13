import { useEffect, useRef, useState } from 'react';

interface CommentaryEntry {
    verse: number;
    text: string;
}

interface CommentaryPanelProps {
    moduleKey: string;
    bookOsis: string;
    chapter: number;
    availableModules: { id: string; key: string; name: string }[];
    onClose: () => void;
    activeVerse?: number | null;
}

export default function CommentaryPanel({
    moduleKey: initialModuleKey,
    bookOsis,
    chapter,
    availableModules,
    onClose,
    activeVerse,
}: CommentaryPanelProps) {
    const [selectedModule, setSelectedModule] = useState(
        initialModuleKey || availableModules[0]?.key || ''
    );
    const [entries, setEntries] = useState<CommentaryEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const verseRefs = useRef<Map<number, HTMLElement>>(new Map());

    // Fetch commentary when module/book/chapter changes
    useEffect(() => {
        if (!selectedModule || !bookOsis) return;

        setLoading(true);
        setError(null);

        fetch(`/commentary/${selectedModule}/${bookOsis}/${chapter}`)
            .then(res => res.json())
            .then(data => {
                setEntries(data.entries || []);
                if (data.error) setError(data.error);
            })
            .catch(() => setError('Failed to load commentary'))
            .finally(() => setLoading(false));
    }, [selectedModule, bookOsis, chapter]);

    // Scroll to active verse
    useEffect(() => {
        if (activeVerse && verseRefs.current.has(activeVerse)) {
            verseRefs.current.get(activeVerse)?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }
    }, [activeVerse, entries]);

    const setVerseRef = (verse: number, el: HTMLElement | null) => {
        if (el) {
            verseRefs.current.set(verse, el);
        } else {
            verseRefs.current.delete(verse);
        }
    };

    return (
        <div className="flex flex-col h-full bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800">
            {/* Header */}
            <div className="flex-none border-b border-gray-200 dark:border-gray-800 px-4 py-3">
                <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                        <svg className="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-white">Commentary</h3>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Module selector */}
                {availableModules.length > 1 ? (
                    <select
                        value={selectedModule}
                        onChange={(e) => setSelectedModule(e.target.value)}
                        className="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        {availableModules.map(mod => (
                            <option key={mod.key} value={mod.key}>{mod.name}</option>
                        ))}
                    </select>
                ) : (
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {availableModules[0]?.name || 'No commentary available'}
                    </p>
                )}
            </div>

            {/* Content */}
            <div ref={panelRef} className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {loading && (
                    <div className="flex items-center justify-center py-12">
                        <div className="h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                    </div>
                )}

                {error && !loading && (
                    <div className="text-center py-8 text-gray-400 dark:text-gray-500">
                        <p className="text-sm">{error}</p>
                    </div>
                )}

                {!loading && !error && entries.length === 0 && (
                    <div className="text-center py-8 text-gray-400 dark:text-gray-500">
                        <svg className="h-10 w-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <p className="text-sm font-medium">No commentary for this passage</p>
                    </div>
                )}

                {!loading && entries.map((entry) => (
                    <div
                        key={entry.verse}
                        ref={(el) => setVerseRef(entry.verse, el)}
                        className={`rounded-lg p-3 transition-colors ${
                            activeVerse === entry.verse
                                ? 'bg-amber-50 dark:bg-amber-900/20 ring-1 ring-amber-300 dark:ring-amber-700'
                                : 'bg-gray-50 dark:bg-gray-800/50'
                        }`}
                    >
                        <div className="flex items-center gap-2 mb-2">
                            <span className="inline-flex items-center justify-center h-6 min-w-[1.5rem] px-1.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-xs font-bold tabular-nums">
                                {entry.verse === 0 ? 'Intro' : entry.verse}
                            </span>
                        </div>
                        <div
                            className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed prose prose-sm dark:prose-invert max-w-none"
                            dangerouslySetInnerHTML={{ __html: entry.text }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
