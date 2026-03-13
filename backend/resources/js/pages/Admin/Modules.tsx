import AppLayout from '@/layouts/AppLayout';
import { Link } from '@inertiajs/react';

interface Module {
    abbreviation: string;
    description: string;
    type: string;
    language: string;
    version: string;
    installed: boolean;
}

interface Props {
    modules: Module[];
}

export default function AdminModules({ modules }: Props) {
    const installed = modules.filter(m => m.installed);
    const available = modules.filter(m => !m.installed);

    return (
        <AppLayout title="Admin — Modules">
            <div className="max-w-6xl mx-auto px-4 py-8">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Modules</h1>
                    <Link href="/admin" className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Dashboard</Link>
                </div>

                <div className="mb-4 flex gap-4 text-sm">
                    <span className="px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">{installed.length} installed</span>
                    <span className="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{available.length} available</span>
                </div>

                {installed.length > 0 && (
                    <>
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Installed</h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                            {installed.map(m => (
                                <div key={m.abbreviation} className="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="font-medium text-gray-900 dark:text-white">{m.abbreviation}</span>
                                        <span className="text-xs px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 rounded-full">{m.type}</span>
                                    </div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{m.description}</p>
                                    <div className="mt-2 flex gap-3 text-xs text-gray-400">
                                        <span>{m.language}</span>
                                        {m.version && <span>v{m.version}</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}

                {available.length > 0 && (
                    <>
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Available</h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {available.map(m => (
                                <div key={m.abbreviation} className="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 opacity-75">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="font-medium text-gray-700 dark:text-gray-300">{m.abbreviation}</span>
                                        <span className="text-xs px-2 py-0.5 bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full">{m.type}</span>
                                    </div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{m.description}</p>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
