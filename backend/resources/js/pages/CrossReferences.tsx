import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/AppLayout';

interface CrossRef {
    reference: string;
    book: string | null;
    chapter: number | null;
    verse: number | null;
    text: string | null;
    type: string;
}

interface Footnote {
    marker?: string;
    text?: string;
    note?: string;
}

interface Props {
    book: string;
    chapter: number;
    verse: number | null;
    module: string;
}

export default function CrossReferences({ book, chapter, verse, module }: Props) {
    const [selectedVerse, setSelectedVerse] = useState(verse ?? 1);
    const [crossRefs, setCrossRefs] = useState<CrossRef[]>([]);
    const [footnotes, setFootnotes] = useState<Footnote[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setLoading(true);
        fetch(`/cross-references/lookup?book=${encodeURIComponent(book)}&chapter=${chapter}&verse=${selectedVerse}&module=${encodeURIComponent(module)}`)
            .then(r => r.json())
            .then(data => {
                setCrossRefs(data.cross_references ?? []);
                setFootnotes(data.footnotes ?? []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [book, chapter, selectedVerse, module]);

    return (
        <AppLayout title="Cross References">
            <div className="max-w-4xl mx-auto px-4 py-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Cross References & Footnotes</h1>
                <p className="text-gray-500 dark:text-gray-400 mb-6">
                    {book} {chapter}:{selectedVerse} ({module})
                </p>

                {/* Verse selector */}
                <div className="flex items-center gap-3 mb-6">
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Verse:</label>
                    <input
                        type="number"
                        min={1}
                        value={selectedVerse}
                        onChange={e => setSelectedVerse(parseInt(e.target.value) || 1)}
                        className="w-20 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                    />
                </div>

                {loading ? (
                    <p className="text-center text-gray-500 py-8">Loading...</p>
                ) : (
                    <div className="space-y-8">
                        {/* Cross References */}
                        <section>
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <span className="text-indigo-500">⛓</span>
                                Cross References
                                <span className="text-sm font-normal text-gray-400">({crossRefs.length})</span>
                            </h2>
                            {crossRefs.length === 0 ? (
                                <p className="text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-6 text-center">No cross references found for this verse.</p>
                            ) : (
                                <div className="space-y-3">
                                    {crossRefs.map((ref, i) => (
                                        <div key={i} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                                            <div className="flex items-start justify-between mb-2">
                                                <a
                                                    href={ref.book ? `/read/${module}/${ref.book}/${ref.chapter}` : '#'}
                                                    className="font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                                >
                                                    {ref.reference}
                                                </a>
                                                <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                                    {ref.type}
                                                </span>
                                            </div>
                                            {ref.text && (
                                                <p className="text-gray-700 dark:text-gray-300 text-sm leading-relaxed italic" dangerouslySetInnerHTML={{ __html: ref.text }} />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>

                        {/* Footnotes */}
                        <section>
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <span className="text-amber-500">📎</span>
                                Footnotes
                                <span className="text-sm font-normal text-gray-400">({footnotes.length})</span>
                            </h2>
                            {footnotes.length === 0 ? (
                                <p className="text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-6 text-center">No footnotes for this verse.</p>
                            ) : (
                                <div className="space-y-2">
                                    {footnotes.map((fn, i) => (
                                        <div key={i} className="flex gap-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3 border border-amber-200 dark:border-amber-800">
                                            {fn.marker && (
                                                <span className="font-mono text-sm font-bold text-amber-600 dark:text-amber-400">{fn.marker}</span>
                                            )}
                                            <p className="text-gray-700 dark:text-gray-300 text-sm">{fn.text || fn.note || JSON.stringify(fn)}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
