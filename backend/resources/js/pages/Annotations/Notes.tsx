import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface NoteData {
    id: string;
    book_osis_id: string;
    chapter_number: number;
    verse_start: number;
    verse_end: number | null;
    module_key: string;
    title: string;
    content: string;
    content_format: string;
    updated_at: string;
}

interface Props {
    notes: NoteData[];
}

export default function Notes({ notes }: Props) {
    const [search, setSearch] = useState('');

    const filtered = search
        ? notes.filter(n =>
            n.title.toLowerCase().includes(search.toLowerCase()) ||
            n.content.toLowerCase().includes(search.toLowerCase())
        )
        : notes;

    const deleteNote = async (id: string) => {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch(`/api/v1/notes/${id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
            credentials: 'same-origin',
        });
        router.reload();
    };

    return (
        <AppLayout title="Notes">
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Notes</h1>
                    <span className="text-sm text-gray-500 dark:text-gray-400">{notes.length} notes</span>
                </div>

                {/* Search */}
                <div className="mb-6">
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search notes..."
                        className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                </div>

                {/* Note list */}
                {filtered.length > 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {filtered.map(note => (
                            <div
                                key={note.id}
                                className="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col"
                            >
                                <div className="flex items-start justify-between mb-2">
                                    <h3 className="text-sm font-semibold text-gray-900 dark:text-white line-clamp-1">
                                        {note.title}
                                    </h3>
                                    <button
                                        onClick={() => deleteNote(note.id)}
                                        className="flex-none ml-2 text-gray-400 hover:text-red-500 transition-colors"
                                        title="Delete"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-3 flex-1">
                                    {note.content}
                                </p>
                                <div className="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500 mt-auto pt-2 border-t border-gray-100 dark:border-gray-800">
                                    <Link
                                        href={`/read/${note.module_key}/${note.book_osis_id}/${note.chapter_number}`}
                                        className="hover:text-indigo-500"
                                    >
                                        {note.book_osis_id} {note.chapter_number}:{note.verse_start}
                                        {note.verse_end ? `-${note.verse_end}` : ''}
                                    </Link>
                                    <span>{new Date(note.updated_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <span className="text-4xl mb-4 block">📝</span>
                        <p className="text-lg font-medium">No notes yet</p>
                        <p className="text-sm mt-1">Select verses in the reader to add notes</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
