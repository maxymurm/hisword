import AppLayout from '@/layouts/AppLayout';

interface BibleStats {
    total_books: number;
    total_chapters: number;
    total_verses: number;
    total_words: number;
    ot: { books: number; chapters: number; verses: number; words: number; longest_book: { name: string; chapters: number; verses: number }; shortest_book: { name: string; chapters: number; verses: number } };
    nt: { books: number; chapters: number; verses: number; words: number; longest_book: { name: string; chapters: number; verses: number }; shortest_book: { name: string; chapters: number; verses: number } };
    longest_verse: { ref: string; words: number };
    shortest_verse: { ref: string; words: number; text: string };
    middle_verse: { ref: string; text: string };
    word_frequency: { word: string; count: number }[];
}

interface ReadingStats {
    logged_in: boolean;
    chapters_read?: number;
    verses_read?: number;
    total_reading_time_minutes?: number;
    current_streak?: number;
    longest_streak?: number;
    favorite_book?: string;
    monthly_reading?: { month: string; chapters: number }[];
}

interface AnnotationStats {
    logged_in: boolean;
    total_bookmarks?: number;
    total_notes?: number;
    total_highlights?: number;
    highlight_colors?: { color: string; count: number }[];
}

interface Props {
    bible_stats: BibleStats;
    reading_stats: ReadingStats;
    annotation_stats: AnnotationStats;
}

export default function Statistics({ bible_stats, reading_stats, annotation_stats }: Props) {
    const maxWordFreq = Math.max(...bible_stats.word_frequency.map(w => w.count));

    return (
        <AppLayout title="Statistics">
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-8">Bible Statistics</h1>

                {/* Overview cards */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-10">
                    <StatCard label="Books" value={bible_stats.total_books.toLocaleString()} icon="📚" />
                    <StatCard label="Chapters" value={bible_stats.total_chapters.toLocaleString()} icon="📖" />
                    <StatCard label="Verses" value={bible_stats.total_verses.toLocaleString()} icon="📜" />
                    <StatCard label="Words" value={bible_stats.total_words.toLocaleString()} icon="✍️" />
                </div>

                {/* OT vs NT comparison */}
                <div className="grid sm:grid-cols-2 gap-6 mb-10">
                    <TestamentCard title="Old Testament" data={bible_stats.ot} color="amber" />
                    <TestamentCard title="New Testament" data={bible_stats.nt} color="blue" />
                </div>

                {/* Fun facts */}
                <div className="mb-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notable Verses</h2>
                    <div className="space-y-3">
                        <FactRow label="Longest verse" value={`${bible_stats.longest_verse.ref} (${bible_stats.longest_verse.words} words)`} />
                        <FactRow label="Shortest verse" value={`${bible_stats.shortest_verse.ref} — "${bible_stats.shortest_verse.text}"`} />
                        <FactRow label="Middle verse" value={`${bible_stats.middle_verse.ref} — "${bible_stats.middle_verse.text}"`} />
                    </div>
                </div>

                {/* Word frequency */}
                <div className="mb-10 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Most Common Words (KJV)</h2>
                    <div className="space-y-2">
                        {bible_stats.word_frequency.map((w, i) => (
                            <div key={i} className="flex items-center gap-3">
                                <span className="w-16 text-sm font-medium text-gray-700 dark:text-gray-300 text-right">{w.word}</span>
                                <div className="flex-1 h-6 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-indigo-500 rounded-full flex items-center justify-end pr-2"
                                        style={{ width: `${(w.count / maxWordFreq) * 100}%` }}
                                    >
                                        <span className="text-xs font-medium text-white">{w.count.toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Personal reading stats */}
                {reading_stats.logged_in && (
                    <div className="mb-10">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Your Reading Stats</h2>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            <StatCard label="Chapters Read" value={String(reading_stats.chapters_read)} icon="📖" />
                            <StatCard label="Current Streak" value={`${reading_stats.current_streak} days`} icon="🔥" />
                            <StatCard label="Longest Streak" value={`${reading_stats.longest_streak} days`} icon="🏆" />
                            <StatCard label="Reading Time" value={`${Math.round((reading_stats.total_reading_time_minutes || 0) / 60)}h`} icon="⏱️" />
                        </div>

                        {/* Monthly chart */}
                        {reading_stats.monthly_reading && (
                            <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                                <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Chapters per Month</h3>
                                <div className="flex items-end gap-2 h-32">
                                    {reading_stats.monthly_reading.map((m, i) => {
                                        const max = Math.max(...reading_stats.monthly_reading!.map(x => x.chapters), 1);
                                        const pct = (m.chapters / max) * 100;
                                        return (
                                            <div key={i} className="flex-1 flex flex-col items-center gap-1">
                                                <span className="text-xs text-gray-500">{m.chapters || ''}</span>
                                                <div className="w-full bg-gray-100 dark:bg-gray-800 rounded-t relative" style={{ height: '100px' }}>
                                                    <div
                                                        className="absolute bottom-0 w-full bg-indigo-500 rounded-t transition-all"
                                                        style={{ height: `${pct}%` }}
                                                    />
                                                </div>
                                                <span className="text-xs text-gray-400">{m.month}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Annotation stats */}
                {annotation_stats.logged_in && (
                    <div className="mb-10">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Your Annotations</h2>
                        <div className="grid grid-cols-3 gap-4">
                            <StatCard label="Bookmarks" value={String(annotation_stats.total_bookmarks)} icon="📑" />
                            <StatCard label="Notes" value={String(annotation_stats.total_notes)} icon="📝" />
                            <StatCard label="Highlights" value={String(annotation_stats.total_highlights)} icon="🖍️" />
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function StatCard({ label, value, icon }: { label: string; value: string; icon: string }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 text-center">
            <div className="text-2xl mb-1">{icon}</div>
            <div className="text-xl font-bold text-gray-900 dark:text-white">{value}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">{label}</div>
        </div>
    );
}

function TestamentCard({ title, data, color }: {
    title: string;
    data: BibleStats['ot'];
    color: 'amber' | 'blue';
}) {
    const bg = color === 'amber' ? 'from-amber-50 to-orange-50 dark:from-amber-950/20 dark:to-orange-950/20' : 'from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20';

    return (
        <div className={`rounded-xl bg-gradient-to-br ${bg} p-5 border border-gray-200 dark:border-gray-800`}>
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-3">{title}</h3>
            <div className="grid grid-cols-2 gap-3 text-sm">
                <div><span className="text-gray-500">Books:</span> <span className="font-medium text-gray-900 dark:text-white">{data.books}</span></div>
                <div><span className="text-gray-500">Chapters:</span> <span className="font-medium text-gray-900 dark:text-white">{data.chapters}</span></div>
                <div><span className="text-gray-500">Verses:</span> <span className="font-medium text-gray-900 dark:text-white">{data.verses.toLocaleString()}</span></div>
                <div><span className="text-gray-500">Words:</span> <span className="font-medium text-gray-900 dark:text-white">{data.words.toLocaleString()}</span></div>
            </div>
            <div className="mt-3 text-xs text-gray-500">
                Longest: {data.longest_book.name} ({data.longest_book.verses} verses) · Shortest: {data.shortest_book.name} ({data.shortest_book.verses} verses)
            </div>
        </div>
    );
}

function FactRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-3">
            <span className="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 shrink-0">{label}</span>
            <span className="text-sm text-gray-700 dark:text-gray-300">{value}</span>
        </div>
    );
}

type BibleStats = Props['bible_stats'];
