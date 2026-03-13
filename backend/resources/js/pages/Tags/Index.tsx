import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Tag {
    id: string;
    name: string;
    color: string;
    description: string | null;
    bookmarks_count: number;
    notes_count: number;
    highlights_count: number;
    created_at: string;
}

interface Props {
    tags: Tag[];
}

const TAG_COLORS: Record<string, string> = {
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
    purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
};

export default function TagsIndex({ tags }: Props) {
    const [showCreate, setShowCreate] = useState(false);
    const [newName, setNewName] = useState('');
    const [newColor, setNewColor] = useState('indigo');
    const [newDesc, setNewDesc] = useState('');

    const createTag = async () => {
        if (!newName.trim()) return;
        await fetch('/tags', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''
                ),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: newName.trim(), color: newColor, description: newDesc.trim() || null }),
        });
        setNewName('');
        setNewDesc('');
        setShowCreate(false);
        router.reload();
    };

    const deleteTag = async (id: string) => {
        await fetch(`/tags/${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''
                ),
            },
            credentials: 'same-origin',
        });
        router.reload();
    };

    return (
        <AppLayout title="Tags & Collections">
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-8">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Tags & Collections</h1>
                    <button
                        onClick={() => setShowCreate(!showCreate)}
                        className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
                    >
                        + New Tag
                    </button>
                </div>

                {/* Create form */}
                {showCreate && (
                    <div className="mb-6 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-4">Create Tag</h3>
                        <div className="space-y-3">
                            <input
                                type="text"
                                placeholder="Tag name"
                                value={newName}
                                onChange={e => setNewName(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                                autoFocus
                            />
                            <div className="flex flex-wrap gap-2">
                                {Object.keys(TAG_COLORS).map(c => (
                                    <button
                                        key={c}
                                        onClick={() => setNewColor(c)}
                                        className={`h-8 w-8 rounded-full border-2 transition-all ${
                                            c === newColor ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent'
                                        } ${TAG_COLORS[c]}`}
                                        title={c}
                                    />
                                ))}
                            </div>
                            <input
                                type="text"
                                placeholder="Description (optional)"
                                value={newDesc}
                                onChange={e => setNewDesc(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                            />
                            <div className="flex justify-end gap-2">
                                <button
                                    onClick={() => setShowCreate(false)}
                                    className="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={createTag}
                                    className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    Create
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tags grid */}
                {tags.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="text-4xl mb-3">🏷️</div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-1">No tags yet</h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Create tags to organize your bookmarks, notes, and highlights.</p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {tags.map(tag => {
                            const total = tag.bookmarks_count + tag.notes_count + tag.highlights_count;
                            return (
                                <div key={tag.id} className="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 hover:shadow-md transition-shadow">
                                    <div className="flex items-start justify-between mb-3">
                                        <Link href={`/tags/${tag.id}`}>
                                            <span className={`inline-block rounded-full px-3 py-1 text-sm font-medium ${TAG_COLORS[tag.color] || TAG_COLORS.gray}`}>
                                                {tag.name}
                                            </span>
                                        </Link>
                                        <button
                                            onClick={() => deleteTag(tag.id)}
                                            className="opacity-0 group-hover:opacity-100 rounded p-1 text-gray-400 hover:text-red-500 transition-all"
                                            title="Delete tag"
                                        >
                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>

                                    {tag.description && (
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">{tag.description}</p>
                                    )}

                                    <div className="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{total} item{total !== 1 ? 's' : ''}</span>
                                        {tag.bookmarks_count > 0 && <span>📑 {tag.bookmarks_count}</span>}
                                        {tag.notes_count > 0 && <span>📝 {tag.notes_count}</span>}
                                        {tag.highlights_count > 0 && <span>🖍️ {tag.highlights_count}</span>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
