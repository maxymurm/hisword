import AppLayout from '@/layouts/AppLayout';

export default function Export() {
    const exports = [
        {
            title: 'Notes',
            description: 'Export all your study notes with verse references.',
            icon: '📝',
            formats: [
                { label: 'Markdown (.md)', url: '/export/notes?format=md' },
                { label: 'HTML (printable)', url: '/export/notes?format=html' },
            ],
        },
        {
            title: 'Bookmarks',
            description: 'Export your bookmarked verses and folders.',
            icon: '📑',
            formats: [
                { label: 'Markdown (.md)', url: '/export/bookmarks?format=md' },
                { label: 'HTML (printable)', url: '/export/bookmarks?format=html' },
            ],
        },
        {
            title: 'Highlights',
            description: 'Export all highlighted verses with color information.',
            icon: '🖍️',
            formats: [
                { label: 'Markdown (.md)', url: '/export/highlights?format=md' },
                { label: 'HTML (printable)', url: '/export/highlights?format=html' },
            ],
        },
    ];

    return (
        <AppLayout title="Export">
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Export Data</h1>
                <p className="text-gray-500 dark:text-gray-400 mb-8">Download your study data in various formats.</p>

                <div className="space-y-4">
                    {exports.map(exp => (
                        <div key={exp.title} className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                            <div className="flex items-start gap-4">
                                <span className="text-3xl">{exp.icon}</span>
                                <div className="flex-1">
                                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{exp.title}</h2>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">{exp.description}</p>
                                    <div className="flex flex-wrap gap-2">
                                        {exp.formats.map(fmt => (
                                            <a
                                                key={fmt.url}
                                                href={fmt.url}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                            >
                                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                {fmt.label}
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="mt-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 p-4">
                    <h3 className="text-sm font-semibold text-indigo-900 dark:text-indigo-200 mb-1">Tips</h3>
                    <ul className="text-sm text-indigo-700 dark:text-indigo-300 space-y-1">
                        <li>• HTML exports can be printed directly from your browser (Ctrl+P)</li>
                        <li>• Markdown files can be opened in any text editor or note-taking app</li>
                        <li>• Your data is always available for download and backup</li>
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
