import { useState, useEffect } from 'react';

interface WordStudyData {
    word: string;
    strongs: string | null;
    definition: {
        strongs: string;
        original: string;
        transliteration: string;
        pronunciation: string;
        part_of_speech: string;
        definition: string;
        kjv_usage: string;
    };
    occurrences: {
        total: number;
        ot: number;
        nt: number;
        by_book: { book: string; count: number }[];
    };
    related_words: { strongs: string; word: string; meaning: string }[];
    cross_references: { reference: string; text: string }[];
}

interface Props {
    word: string;
    strongs?: string | null;
    onClose: () => void;
}

export default function WordStudyPanel({ word, strongs, onClose }: Props) {
    const [data, setData] = useState<WordStudyData | null>(null);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState<'definition' | 'occurrences' | 'references'>('definition');

    useEffect(() => {
        setLoading(true);
        const params = new URLSearchParams({ word });
        if (strongs) params.set('strongs', strongs);

        fetch(`/word-study?${params}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => r.ok ? r.json() : null)
            .then(d => { setData(d); setLoading(false); })
            .catch(() => setLoading(false));
    }, [word, strongs]);

    return (
        <div className="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-white dark:bg-gray-900 shadow-2xl border-l border-gray-200 dark:border-gray-800 flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">{word}</h2>
                    {strongs && <span className="text-xs font-mono text-indigo-600 dark:text-indigo-400">{strongs}</span>}
                </div>
                <button
                    onClick={onClose}
                    className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                    aria-label="Close"
                >
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-gray-200 dark:border-gray-800">
                {(['definition', 'occurrences', 'references'] as const).map(tab => (
                    <button
                        key={tab}
                        onClick={() => setActiveTab(tab)}
                        className={`flex-1 px-4 py-2.5 text-sm font-medium capitalize transition-colors ${
                            activeTab === tab
                                ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400'
                                : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-400'
                        }`}
                    >
                        {tab}
                    </button>
                ))}
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-4">
                {loading ? (
                    <div className="flex items-center justify-center h-32">
                        <div className="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600" />
                    </div>
                ) : !data ? (
                    <p className="text-sm text-gray-500 text-center py-8">No data available</p>
                ) : (
                    <>
                        {activeTab === 'definition' && <DefinitionTab def={data.definition} related={data.related_words} />}
                        {activeTab === 'occurrences' && <OccurrencesTab occ={data.occurrences} />}
                        {activeTab === 'references' && <ReferencesTab refs={data.cross_references} />}
                    </>
                )}
            </div>
        </div>
    );
}

function DefinitionTab({ def, related }: { def: WordStudyData['definition']; related: WordStudyData['related_words'] }) {
    return (
        <div className="space-y-5">
            {def.original && (
                <div className="text-center">
                    <div className="text-3xl font-semibold text-gray-900 dark:text-white mb-1" dir="auto">
                        {def.original}
                    </div>
                    {def.transliteration && (
                        <div className="text-sm text-gray-500">{def.transliteration} — <span className="italic">{def.pronunciation}</span></div>
                    )}
                    {def.part_of_speech && (
                        <span className="inline-block mt-1 rounded-full bg-indigo-50 dark:bg-indigo-950/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            {def.part_of_speech}
                        </span>
                    )}
                </div>
            )}

            <div>
                <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Definition</h3>
                <p className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{def.definition}</p>
            </div>

            {def.kjv_usage && (
                <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">KJV Usage</h3>
                    <p className="text-sm text-gray-600 dark:text-gray-400">{def.kjv_usage}</p>
                </div>
            )}

            {related.length > 0 && (
                <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Related Words</h3>
                    <div className="space-y-2">
                        {related.map((r, i) => (
                            <div key={i} className="rounded-lg bg-gray-50 dark:bg-gray-800 px-3 py-2">
                                <div className="text-sm font-medium text-gray-900 dark:text-white">{r.word}</div>
                                <div className="text-xs text-gray-500">{r.strongs} — {r.meaning}</div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function OccurrencesTab({ occ }: { occ: WordStudyData['occurrences'] }) {
    const max = Math.max(...occ.by_book.map(b => b.count), 1);

    return (
        <div className="space-y-5">
            {/* Summary */}
            <div className="grid grid-cols-3 gap-3">
                <StatCard label="Total" value={occ.total} />
                <StatCard label="OT" value={occ.ot} />
                <StatCard label="NT" value={occ.nt} />
            </div>

            {/* Book distribution */}
            <div>
                <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">By Book</h3>
                <div className="space-y-2">
                    {occ.by_book.map((b, i) => (
                        <div key={i} className="flex items-center gap-3">
                            <span className="w-28 text-sm text-gray-700 dark:text-gray-300 truncate">{b.book}</span>
                            <div className="flex-1 h-5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-indigo-500 rounded-full transition-all"
                                    style={{ width: `${(b.count / max) * 100}%` }}
                                />
                            </div>
                            <span className="w-8 text-right text-xs font-medium text-gray-500">{b.count}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function ReferencesTab({ refs }: { refs: WordStudyData['cross_references'] }) {
    return (
        <div className="space-y-3">
            <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Key Passages</h3>
            {refs.map((ref, i) => (
                <div key={i} className="rounded-lg border border-gray-200 dark:border-gray-800 px-3 py-2.5">
                    <div className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 mb-1">{ref.reference}</div>
                    <p className="text-sm text-gray-600 dark:text-gray-400 italic">"{ref.text}"</p>
                </div>
            ))}
        </div>
    );
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg bg-gray-50 dark:bg-gray-800 px-3 py-2 text-center">
            <div className="text-lg font-bold text-gray-900 dark:text-white">{value}</div>
            <div className="text-xs text-gray-500">{label}</div>
        </div>
    );
}
