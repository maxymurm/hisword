import AppLayout from '@/layouts/AppLayout';
import { Link } from '@inertiajs/react';

interface DevotionalModule {
    key: string;
    name: string;
    description: string | null;
    language: string | null;
}

interface Props {
    devotionals: DevotionalModule[];
}

export default function DevotionalsIndex({ devotionals }: Props) {
    return (
        <AppLayout title="Devotionals">
            <div className="max-w-3xl mx-auto px-4 py-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Devotionals</h1>
                <p className="text-gray-500 dark:text-gray-400 mb-8">Daily devotional readings from installed modules.</p>

                {devotionals.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="text-4xl mb-4">🙏</div>
                        <p className="text-gray-500 dark:text-gray-400 mb-4">No devotional modules installed.</p>
                        <Link href="/modules?type=devotional" className="text-indigo-600 dark:text-indigo-400 hover:underline">
                            Browse devotional modules &rarr;
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {devotionals.map(d => (
                            <Link
                                key={d.key}
                                href={`/devotionals/${d.key}`}
                                className="block p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-indigo-500 transition"
                            >
                                <div className="flex items-start gap-4">
                                    <div className="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center text-2xl">
                                        🙏
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="font-semibold text-gray-900 dark:text-white">{d.name}</h3>
                                        {d.description && (
                                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{d.description}</p>
                                        )}
                                        {d.language && (
                                            <span className="text-xs text-gray-400 mt-2 inline-block">{d.language}</span>
                                        )}
                                    </div>
                                    <span className="text-gray-400">&rarr;</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
