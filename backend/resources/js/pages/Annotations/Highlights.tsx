import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

const COLORS: Record<string, { label: string; bg: string; ring: string }> = {
    yellow:  { label: 'Yellow',  bg: 'bg-yellow-200',  ring: 'ring-yellow-400'  },
    green:   { label: 'Green',   bg: 'bg-green-200',   ring: 'ring-green-400'   },
    blue:    { label: 'Blue',    bg: 'bg-blue-200',    ring: 'ring-blue-400'    },
    pink:    { label: 'Pink',    bg: 'bg-pink-200',    ring: 'ring-pink-400'    },
    purple:  { label: 'Purple',  bg: 'bg-purple-200',  ring: 'ring-purple-400'  },
    orange:  { label: 'Orange',  bg: 'bg-orange-200',  ring: 'ring-orange-400'  },
    red:     { label: 'Red',     bg: 'bg-red-200',     ring: 'ring-red-400'     },
    teal:    { label: 'Teal',    bg: 'bg-teal-200',    ring: 'ring-teal-400'    },
};

interface HighlightData {
    id: string;
    book_osis_id: string;
    chapter_number: number;
    verse_number: number;
    module_key: string;
    color: string;
    updated_at: string;
    verse?: { text: string } | null;
}

interface Props {
    highlights: HighlightData[];
}

export default function Highlights({ highlights }: Props) {
    const [colorFilter, setColorFilter] = useState<string | null>(null);

    const filtered = colorFilter
        ? highlights.filter(h => h.color === colorFilter)
        : highlights;

    const deleteHighlight = async (id: string) => {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch(`/api/v1/highlights/${id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
            credentials: 'same-origin',
        });
        router.reload();
    };

    return (
        <AppLayout title="Highlights">
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Highlights</h1>
                    <span className="text-sm text-gray-500 dark:text-gray-400">{highlights.length} highlights</span>
                </div>

                {/* Color filter chips */}
                <div className="flex flex-wrap gap-2 mb-6">
                    <button
                        onClick={() => setColorFilter(null)}
                        className={`rounded-full px-3 py-1 text-xs font-medium border transition-colors ${
                            !colorFilter
                                ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                                : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-400'
                        }`}
                    >
                        All
                    </button>
                    {Object.entries(COLORS).map(([key, { label, bg }]) => (
                        <button
                            key={key}
                            onClick={() => setColorFilter(colorFilter === key ? null : key)}
                            className={`flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium border transition-colors ${
                                colorFilter === key
                                    ? 'border-gray-900 dark:border-white bg-gray-50 dark:bg-gray-800'
                                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'
                            }`}
                        >
                            <span className={`inline-block h-2.5 w-2.5 rounded-full ${bg}`} />
                            {label}
                        </button>
                    ))}
                </div>

                {/* Highlight list */}
                {filtered.length > 0 ? (
                    <div className="space-y-2">
                        {filtered.map(h => {
                            const colorInfo = COLORS[h.color] || COLORS.yellow;
                            return (
                                <div
                                    key={h.id}
                                    className="flex items-center gap-4 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-4 py-3 shadow-sm"
                                >
                                    <span className={`flex-none h-4 w-4 rounded-full ${colorInfo.bg}`} />
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={`/read/${h.module_key}/${h.book_osis_id}/${h.chapter_number}`}
                                            className="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                        >
                                            {h.book_osis_id} {h.chapter_number}:{h.verse_number}
                                        </Link>
                                        {h.verse?.text && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">
                                                {h.verse.text}
                                            </p>
                                        )}
                                    </div>
                                    <span className="flex-none text-xs text-gray-400">
                                        {new Date(h.updated_at).toLocaleDateString()}
                                    </span>
                                    <button
                                        onClick={() => deleteHighlight(h.id)}
                                        className="flex-none text-gray-400 hover:text-red-500 transition-colors"
                                        title="Remove highlight"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <span className="text-4xl mb-4 block">🎨</span>
                        <p className="text-lg font-medium">No highlights yet</p>
                        <p className="text-sm mt-1">Select verses in the reader to highlight them</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
