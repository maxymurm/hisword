import AppLayout from '@/layouts/AppLayout';
import { router } from '@inertiajs/react';

interface HistoryItem {
    id: number;
    module_key: string;
    book_osis_id: string;
    chapter_number: number;
    verse_number: number | null;
    action: string;
    created_at: string;
}

interface Props {
    history: {
        data: HistoryItem[];
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}

function timeAgo(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(dateStr).toLocaleDateString();
}

function groupByDate(items: HistoryItem[]): Record<string, HistoryItem[]> {
    const groups: Record<string, HistoryItem[]> = {};
    for (const item of items) {
        const date = new Date(item.created_at).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        if (!groups[date]) groups[date] = [];
        groups[date].push(item);
    }
    return groups;
}

export default function History({ history }: Props) {
    const grouped = groupByDate(history.data);

    function clearHistory() {
        if (confirm('Clear all reading history?')) {
            router.delete('/history');
        }
    }

    return (
        <AppLayout title="Reading History">
            <div className="max-w-3xl mx-auto px-4 py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Reading History</h1>
                        <p className="text-gray-500 dark:text-gray-400">Recently read passages</p>
                    </div>
                    {history.data.length > 0 && (
                        <button onClick={clearHistory} className="text-sm text-red-500 hover:text-red-600">
                            Clear All
                        </button>
                    )}
                </div>

                {history.data.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="text-4xl mb-4">📖</div>
                        <p className="text-gray-500 dark:text-gray-400">No reading history yet. Start reading to see your history here.</p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {Object.entries(grouped).map(([date, items]) => (
                            <div key={date}>
                                <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3 uppercase tracking-wide">{date}</h2>
                                <div className="space-y-2">
                                    {items.map(item => (
                                        <a
                                            key={item.id}
                                            href={`/read/${item.module_key}/${item.book_osis_id}/${item.chapter_number}`}
                                            className="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-indigo-500 transition"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                                    📖
                                                </div>
                                                <div>
                                                    <div className="font-medium text-gray-900 dark:text-white">
                                                        {item.book_osis_id} {item.chapter_number}
                                                        {item.verse_number ? `:${item.verse_number}` : ''}
                                                    </div>
                                                    <div className="text-sm text-gray-500 dark:text-gray-400">{item.module_key}</div>
                                                </div>
                                            </div>
                                            <span className="text-xs text-gray-400">{timeAgo(item.created_at)}</span>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ))}

                        {/* Pagination */}
                        {(history.prev_page_url || history.next_page_url) && (
                            <div className="flex justify-center gap-3 pt-4">
                                {history.prev_page_url && (
                                    <a href={history.prev_page_url} className="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                                        &larr; Newer
                                    </a>
                                )}
                                {history.next_page_url && (
                                    <a href={history.next_page_url} className="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                                        Older &rarr;
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
