import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Note {
    id: string;
    title: string | null;
    content: string | null;
    book_osis_id: string | null;
    chapter_number: number | null;
    verse_start: number | null;
    module_key: string | null;
    updated_at: string;
    created_at: string;
}

interface PaginatedNotes {
    data: Note[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    notes: PaginatedNotes;
}

export default function StudyPadIndex({ notes }: Props) {
    const [creating, setCreating] = useState(false);
    const [title, setTitle] = useState('');

    const createNote = async () => {
        if (!title.trim()) return;
        const resp = await fetch('/study-pad', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ title: title.trim(), content: '' }),
        });
        if (resp.ok) {
            const note = await resp.json();
            router.visit(`/study-pad/${note.id}`);
        }
    };

    const deleteNote = async (id: string) => {
        await fetch(`/study-pad/${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            credentials: 'same-origin',
        });
        router.reload();
    };

    return (
        <AppLayout title="Study Pad">
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Study Pad</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Your personal Bible study workspace</p>
                    </div>
                    <button
                        onClick={() => setCreating(!creating)}
                        className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
                    >
                        + New Note
                    </button>
                </div>

                {creating && (
                    <div className="mb-6 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                        <input
                            type="text"
                            placeholder="Note title..."
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && createNote()}
                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm mb-3"
                            autoFocus
                        />
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setCreating(false)} className="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800">Cancel</button>
                            <button onClick={createNote} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create</button>
                        </div>
                    </div>
                )}

                {notes.data.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="text-4xl mb-3">📓</div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-1">No study notes yet</h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Create a note to start your Bible study journal.</p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {notes.data.map(note => (
                            <div key={note.id} className="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 hover:shadow-md transition-shadow">
                                <div className="flex items-start justify-between">
                                    <Link href={`/study-pad/${note.id}`} className="flex-1">
                                        <h3 className="text-base font-semibold text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                            {note.title || 'Untitled'}
                                        </h3>
                                        {note.content && (
                                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                {note.content.substring(0, 150)}
                                            </p>
                                        )}
                                        <div className="flex items-center gap-3 mt-2 text-xs text-gray-400">
                                            <span>{new Date(note.updated_at).toLocaleDateString()}</span>
                                            {note.book_osis_id && (
                                                <span className="text-indigo-500">
                                                    {note.book_osis_id} {note.chapter_number}{note.verse_start ? `:${note.verse_start}` : ''}
                                                </span>
                                            )}
                                            {note.module_key && <span className="font-mono">{note.module_key}</span>}
                                        </div>
                                    </Link>
                                    <button
                                        onClick={() => deleteNote(note.id)}
                                        className="opacity-0 group-hover:opacity-100 rounded p-1 text-gray-400 hover:text-red-500 transition-all ml-2"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {notes.last_page > 1 && (
                    <div className="flex justify-center gap-2 mt-8">
                        {Array.from({ length: notes.last_page }, (_, i) => i + 1).map(page => (
                            <Link
                                key={page}
                                href={`/study-pad?page=${page}`}
                                className={`rounded-lg px-3 py-1.5 text-sm ${
                                    page === notes.current_page
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                                }`}
                            >
                                {page}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
