import AppLayout from '@/layouts/AppLayout';
import { Link } from '@inertiajs/react';

interface Props {
    stats: {
        users: number;
        modules_installed: number;
        modules_available: number;
        bookmarks: number;
        highlights: number;
        notes: number;
    };
    recent_users: { id: number; name: string; email: string; created_at: string }[];
}

export default function AdminDashboard({ stats, recent_users }: Props) {
    const statCards = [
        { label: 'Users', value: stats.users, icon: '👥', color: 'indigo' },
        { label: 'Installed Modules', value: stats.modules_installed, icon: '📚', color: 'green' },
        { label: 'Available Modules', value: stats.modules_available, icon: '📦', color: 'amber' },
        { label: 'Bookmarks', value: stats.bookmarks, icon: '🔖', color: 'blue' },
        { label: 'Highlights', value: stats.highlights, icon: '🖍', color: 'yellow' },
        { label: 'Notes', value: stats.notes, icon: '📝', color: 'purple' },
    ];

    return (
        <AppLayout title="Admin Dashboard">
            <div className="max-w-6xl mx-auto px-4 py-8">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Admin Dashboard</h1>
                        <p className="text-gray-500 dark:text-gray-400">System overview and management</p>
                    </div>
                    <div className="flex gap-3">
                        <Link href="/admin/users" className="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 text-sm font-medium">
                            Manage Users
                        </Link>
                        <Link href="/admin/modules" className="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 text-sm font-medium">
                            Manage Modules
                        </Link>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
                    {statCards.map(s => (
                        <div key={s.label} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                            <div className="flex items-center gap-3 mb-2">
                                <span className="text-2xl">{s.icon}</span>
                                <span className="text-sm text-gray-500 dark:text-gray-400">{s.label}</span>
                            </div>
                            <div className="text-3xl font-bold text-gray-900 dark:text-white">{s.value.toLocaleString()}</div>
                        </div>
                    ))}
                </div>

                {/* Recent Users */}
                <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div className="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 className="font-semibold text-gray-900 dark:text-white">Recent Users</h2>
                    </div>
                    <div className="divide-y divide-gray-200 dark:divide-gray-700">
                        {recent_users.map(u => (
                            <div key={u.id} className="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <div className="font-medium text-gray-900 dark:text-white">{u.name}</div>
                                    <div className="text-sm text-gray-500 dark:text-gray-400">{u.email}</div>
                                </div>
                                <span className="text-xs text-gray-400">{new Date(u.created_at).toLocaleDateString()}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
