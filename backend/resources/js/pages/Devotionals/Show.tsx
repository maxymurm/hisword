import AppLayout from '@/layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

interface Props {
    module: { key: string; name: string; description: string | null };
    entry: { date: string; text: string | null };
    today: string;
}

export default function DevotionalShow({ module, entry, today }: Props) {
    function navigateDate(offset: number) {
        const [m, d] = entry.date.split('.').map(Number);
        const date = new Date(2026, m - 1, d + offset);
        const newDate = `${String(date.getMonth() + 1).padStart(2, '0')}.${String(date.getDate()).padStart(2, '0')}`;
        router.visit(`/devotionals/${module.key}?date=${newDate}`);
    }

    return (
        <AppLayout title={`${module.name} — Devotional`}>
            <div className="max-w-3xl mx-auto px-4 py-8">
                <Link href="/devotionals" className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline mb-4 inline-block">
                    &larr; All Devotionals
                </Link>

                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-1">{module.name}</h1>

                {/* Date navigation */}
                <div className="flex items-center gap-4 mb-6">
                    <button onClick={() => navigateDate(-1)} className="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                        &larr; Previous
                    </button>
                    <span className="font-medium text-gray-900 dark:text-white">
                        {entry.date}
                        {entry.date === today && (
                            <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400">Today</span>
                        )}
                    </span>
                    <button onClick={() => navigateDate(1)} className="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                        Next &rarr;
                    </button>
                    {entry.date !== today && (
                        <button
                            onClick={() => router.visit(`/devotionals/${module.key}`)}
                            className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Go to today
                        </button>
                    )}
                </div>

                {/* Devotional content */}
                <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    {entry.text ? (
                        <div
                            className="prose dark:prose-invert max-w-none leading-relaxed"
                            dangerouslySetInnerHTML={{ __html: entry.text }}
                        />
                    ) : (
                        <p className="text-center text-gray-500 dark:text-gray-400 py-8">
                            No devotional entry for this date.
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
