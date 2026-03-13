import { useState, useRef, useCallback } from 'react';
import { Link } from '@inertiajs/react';
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
}

interface Props {
    note: Note;
}

export default function StudyPadEditor({ note }: Props) {
    const [title, setTitle] = useState(note.title || '');
    const [content, setContent] = useState(note.content || '');
    const [saved, setSaved] = useState(true);
    const [saving, setSaving] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);

    const save = useCallback(async (t: string, c: string) => {
        setSaving(true);
        await fetch(`/study-pad/${note.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ title: t, content: c }),
        });
        setSaving(false);
        setSaved(true);
    }, [note.id]);

    const handleChange = (field: 'title' | 'content', value: string) => {
        if (field === 'title') setTitle(value);
        else setContent(value);
        setSaved(false);

        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            save(field === 'title' ? value : title, field === 'content' ? value : content);
        }, 1000);
    };

    const wordCount = content.trim() ? content.trim().split(/\s+/).length : 0;

    return (
        <AppLayout title={title || 'Study Pad'}>
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                {/* Toolbar */}
                <div className="flex items-center justify-between mb-4">
                    <Link href="/study-pad" className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        ← All Notes
                    </Link>
                    <div className="flex items-center gap-3 text-xs text-gray-400">
                        <span>{wordCount} word{wordCount !== 1 ? 's' : ''}</span>
                        <span>
                            {saving ? (
                                <span className="flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-blue-500 animate-pulse" />
                                    Saving…
                                </span>
                            ) : saved ? (
                                <span className="flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-green-500" />
                                    Saved
                                </span>
                            ) : (
                                <span className="flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-amber-500" />
                                    Unsaved
                                </span>
                            )}
                        </span>
                    </div>
                </div>

                {/* Editor */}
                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                    {/* Title */}
                    <input
                        type="text"
                        value={title}
                        onChange={e => handleChange('title', e.target.value)}
                        placeholder="Note title..."
                        className="w-full border-b border-gray-100 dark:border-gray-800 bg-transparent px-6 py-4 text-xl font-bold text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none"
                    />

                    {/* Markdown formatting toolbar */}
                    <div className="flex items-center gap-1 px-4 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                        <ToolbarBtn label="Bold" icon="B" style="font-bold" onClick={() => insertMarkdown('**', '**')} />
                        <ToolbarBtn label="Italic" icon="I" style="italic" onClick={() => insertMarkdown('*', '*')} />
                        <ToolbarBtn label="Heading" icon="H" style="font-bold" onClick={() => insertMarkdown('## ', '')} />
                        <span className="w-px h-5 bg-gray-300 dark:bg-gray-700 mx-1" />
                        <ToolbarBtn label="List" icon="•" onClick={() => insertMarkdown('- ', '')} />
                        <ToolbarBtn label="Numbered" icon="1." onClick={() => insertMarkdown('1. ', '')} />
                        <ToolbarBtn label="Quote" icon="❝" onClick={() => insertMarkdown('> ', '')} />
                        <span className="w-px h-5 bg-gray-300 dark:bg-gray-700 mx-1" />
                        <ToolbarBtn label="Verse ref" icon="📖" onClick={() => insertMarkdown('[', '](verse://)')} />
                    </div>

                    {/* Content area */}
                    <textarea
                        value={content}
                        onChange={e => handleChange('content', e.target.value)}
                        placeholder="Start writing your study notes...&#10;&#10;Use Markdown for formatting: **bold**, *italic*, ## headings, - lists&#10;Link to verses: [John 3:16](verse://John.3.16)"
                        className="w-full min-h-[400px] resize-y bg-transparent px-6 py-4 text-gray-700 dark:text-gray-300 leading-relaxed focus:outline-none font-mono text-sm"
                        id="study-pad-editor"
                    />
                </div>

                {/* Reference info */}
                {note.book_osis_id && (
                    <div className="mt-4 text-sm text-gray-500">
                        Linked: {note.book_osis_id} {note.chapter_number}
                        {note.verse_start && `:${note.verse_start}`}
                        {note.module_key && ` (${note.module_key})`}
                    </div>
                )}
            </div>
        </AppLayout>
    );

    function insertMarkdown(before: string, after: string) {
        const textarea = document.getElementById('study-pad-editor') as HTMLTextAreaElement;
        if (!textarea) return;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = content.substring(start, end);
        const newContent = content.substring(0, start) + before + selected + after + content.substring(end);
        setContent(newContent);
        setSaved(false);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => save(title, newContent), 1000);
        // Restore cursor position
        setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(start + before.length, end + before.length);
        }, 0);
    }
}

function ToolbarBtn({ label, icon, style, onClick }: { label: string; icon: string; style?: string; onClick: () => void }) {
    return (
        <button
            onClick={onClick}
            title={label}
            className={`rounded px-2 py-1 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors ${style || ''}`}
        >
            {icon}
        </button>
    );
}
