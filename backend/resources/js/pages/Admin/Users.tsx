import AppLayout from '@/layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    bookmarks_count: number;
    highlights_count: number;
    notes_count: number;
}

interface Props {
    users: { data: User[]; current_page: number; last_page: number; next_page_url: string | null; prev_page_url: string | null };
    search: string;
}

export default function AdminUsers({ users, search }: Props) {
    const [query, setQuery] = useState(search);

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.visit(`/admin/users?search=${encodeURIComponent(query)}`);
    }

    return (
        <AppLayout title="Admin — Users">
            <div className="max-w-6xl mx-auto px-4 py-8">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Users</h1>
                    <Link href="/admin" className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Dashboard</Link>
                </div>

                <form onSubmit={handleSearch} className="mb-6">
                    <input
                        type="text"
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        placeholder="Search users..."
                        className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                    />
                </form>

                <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table className="w-full text-left">
                        <thead className="bg-gray-50 dark:bg-gray-900 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <tr>
                                <th className="px-5 py-3">Name</th>
                                <th className="px-5 py-3">Email</th>
                                <th className="px-5 py-3 text-center">Bookmarks</th>
                                <th className="px-5 py-3 text-center">Highlights</th>
                                <th className="px-5 py-3 text-center">Notes</th>
                                <th className="px-5 py-3">Joined</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {users.data.map(u => (
                                <tr key={u.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td className="px-5 py-3 font-medium text-gray-900 dark:text-white">{u.name}</td>
                                    <td className="px-5 py-3 text-gray-500 dark:text-gray-400">{u.email}</td>
                                    <td className="px-5 py-3 text-center text-gray-600 dark:text-gray-300">{u.bookmarks_count}</td>
                                    <td className="px-5 py-3 text-center text-gray-600 dark:text-gray-300">{u.highlights_count}</td>
                                    <td className="px-5 py-3 text-center text-gray-600 dark:text-gray-300">{u.notes_count}</td>
                                    <td className="px-5 py-3 text-xs text-gray-400">{new Date(u.created_at).toLocaleDateString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {(users.prev_page_url || users.next_page_url) && (
                    <div className="flex justify-center gap-3 mt-4">
                        {users.prev_page_url && <a href={users.prev_page_url} className="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm">&larr; Prev</a>}
                        <span className="px-4 py-2 text-sm text-gray-500">{users.current_page} / {users.last_page}</span>
                        {users.next_page_url && <a href={users.next_page_url} className="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm">Next &rarr;</a>}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
