import AppLayout from '@/layouts/AppLayout';
import type { PageProps } from '@/types';
import VerseOfDayWidget from '@/components/VerseOfDayWidget';

export default function Home({ auth }: PageProps) {
    return (
        <AppLayout title="Home">
            <div className="space-y-8">
                {/* Verse of the Day */}
                <VerseOfDayWidget />

                {/* Hero / Welcome */}
                <div className="text-center py-12">
                    <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100 sm:text-5xl">
                        HisWord
                    </h1>
                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Read, study, and annotate the Bible. Your highlights, bookmarks, and notes sync across all your devices.
                    </p>
                </div>

                {/* Quick Actions */}
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <QuickCard
                        icon="📖"
                        title="Read"
                        description="Open the Bible reader with commentary, cross-references, and more."
                        href="/read"
                    />
                    <QuickCard
                        icon="🔍"
                        title="Search"
                        description="Full-text search across all installed Bible modules."
                        href="/search"
                    />
                    <QuickCard
                        icon="�"
                        title="Modules"
                        description="Browse and install Bible modules, commentaries, dictionaries, and more."
                        href="/modules"
                    />
                    <QuickCard
                        icon="�📝"
                        title="Notes"
                        description="View and manage your verse notes and study journal."
                        href="/notes"
                    />
                    <QuickCard
                        icon="🔖"
                        title="Bookmarks"
                        description="Access your saved bookmarks organized by folders."
                        href="/bookmarks"
                    />
                    <QuickCard
                        icon="📅"
                        title="Reading Plans"
                        description="Follow structured reading plans to read through the Bible."
                        href="/plans"
                    />
                    <QuickCard
                        icon="⚙️"
                        title="Settings"
                        description="Customize appearance, sync, and module preferences."
                        href="/settings"
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function QuickCard({ icon, title, description, href }: {
    icon: string;
    title: string;
    description: string;
    href: string;
}) {
    return (
        <a
            href={href}
            className="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-700 transition-all"
        >
            <div className="text-3xl mb-3">{icon}</div>
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                {title}
            </h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>
        </a>
    );
}
