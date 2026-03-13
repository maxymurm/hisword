import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface BookmarkFolder {
    id: string;
    name: string;
    color: string;
    sort_order: number;
}

interface Bookmark {
    id: string;
    folder_id: string | null;
    book_osis_id: string;
    chapter_number: number;
    verse_start: number;
    verse_end: number | null;
    module_key: string;
    label: string;
    description: string | null;
    created_at: string;
}

interface Props {
    folders: BookmarkFolder[];
    bookmarks: Bookmark[];
}

export default function Bookmarks({ folders, bookmarks }: Props) {
    const [selectedFolder, setSelectedFolder] = useState<string | null>(null);

    const filtered = selectedFolder
        ? bookmarks.filter(b => b.folder_id === selectedFolder)
        : bookmarks;

    const deleteBookmark = async (id: string) => {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch(`/api/v1/bookmarks/${id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
            credentials: 'same-origin',
        });
        router.reload();
    };

    return (
        <AppLayout title="Bookmarks">
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Bookmarks</h1>
                    <span className="text-sm text-gray-500 dark:text-gray-400">{bookmarks.length} bookmarks</span>
                </div>

                {/* Folder tabs */}
                <div className="flex gap-2 mb-6 overflow-x-auto pb-2">
                    <button
                        onClick={() => setSelectedFolder(null)}
                        className={`px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                            selectedFolder === null
                                ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'
                        }`}
                    >
                        All ({bookmarks.length})
                    </button>
                    {folders.map(f => {
                        const count = bookmarks.filter(b => b.folder_id === f.id).length;
                        return (
                            <button
                                key={f.id}
                                onClick={() => setSelectedFolder(f.id)}
                                className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                                    selectedFolder === f.id
                                        ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'
                                }`}
                            >
                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: f.color || '#6366f1' }} />
                                {f.name} ({count})
                            </button>
                        );
                    })}
                </div>

                {/* Bookmark list */}
                {filtered.length > 0 ? (
                    <div className="space-y-2">
                        {filtered.map(bm => (
                            <div
                                key={bm.id}
                                className="flex items-start gap-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 shadow-sm hover:shadow-md transition-shadow"
                            >
                                <div className="flex-none mt-0.5 text-lg">🔖</div>
                                <div className="flex-1 min-w-0">
                                    <Link
                                        href={`/read/${bm.module_key}/${bm.book_osis_id}/${bm.chapter_number}`}
                                        className="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                    >
                                        {bm.label}
                                    </Link>
                                    {bm.description && (
                                        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{bm.description}</p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        {bm.book_osis_id} {bm.chapter_number}:{bm.verse_start}
                                        {bm.verse_end && bm.verse_end !== bm.verse_start ? `-${bm.verse_end}` : ''}
                                        {' · '}{bm.module_key}
                                    </p>
                                </div>
                                <button
                                    onClick={() => deleteBookmark(bm.id)}
                                    className="flex-none text-gray-400 hover:text-red-500 transition-colors"
                                    title="Delete"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <span className="text-4xl mb-4 block">🔖</span>
                        <p className="text-lg font-medium">No bookmarks yet</p>
                        <p className="text-sm mt-1">Select verses in the reader to add bookmarks</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
