import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

// ── Types ───────────────────────────────────────────────────────

interface VerseActionProps {
    selectedVerses: number[];
    bookOsis: string;
    chapter: number;
    moduleKey: string;
    existingHighlight?: string | null;
    onClose: () => void;
    onShareImage?: (verseText: string, reference: string) => void;
    position: { top: number; left: number };
}

const HIGHLIGHT_COLORS = [
    { key: 'yellow', bg: 'bg-yellow-400', label: 'Yellow' },
    { key: 'green', bg: 'bg-green-400', label: 'Green' },
    { key: 'blue', bg: 'bg-blue-400', label: 'Blue' },
    { key: 'pink', bg: 'bg-pink-400', label: 'Pink' },
    { key: 'purple', bg: 'bg-purple-400', label: 'Purple' },
    { key: 'orange', bg: 'bg-orange-400', label: 'Orange' },
    { key: 'red', bg: 'bg-red-400', label: 'Red' },
    { key: 'teal', bg: 'bg-teal-400', label: 'Teal' },
];

// ── Verse Action Popover ────────────────────────────────────────

export default function VerseActionPopover({
    selectedVerses,
    bookOsis,
    chapter,
    moduleKey,
    existingHighlight,
    onClose,
    onShareImage,
    position,
}: VerseActionProps) {
    const { auth } = usePage<PageProps>().props;
    const [showHighlightPicker, setShowHighlightPicker] = useState(false);
    const [showBookmarkForm, setShowBookmarkForm] = useState(false);
    const [showNoteForm, setShowNoteForm] = useState(false);
    const [saving, setSaving] = useState(false);

    const verseStart = Math.min(...selectedVerses);
    const verseEnd = Math.max(...selectedVerses);
    const reference = `${bookOsis} ${chapter}:${verseStart}${verseEnd > verseStart ? `-${verseEnd}` : ''}`;

    if (!auth.user) {
        return (
            <div
                className="fixed z-50 bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-4 w-64"
                style={{ top: position.top, left: position.left }}
            >
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-2">
                    Sign in to annotate verses
                </p>
                <div className="flex gap-2">
                    <a href="/login" className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                        Sign in
                    </a>
                    <button onClick={onClose} className="ml-auto text-sm text-gray-400 hover:text-gray-600">
                        Close
                    </button>
                </div>
            </div>
        );
    }

    const apiCall = async (method: string, url: string, body?: object) => {
        setSaving(true);
        try {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const resp = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : undefined,
            });
            return resp.ok;
        } finally {
            setSaving(false);
        }
    };

    const handleHighlight = async (color: string) => {
        for (const verse of selectedVerses) {
            await apiCall('POST', '/api/v1/highlights', {
                book_osis_id: bookOsis,
                chapter_number: chapter,
                verse_number: verse,
                color,
                module_key: moduleKey,
            });
        }
        setShowHighlightPicker(false);
        onClose();
        router.reload({ only: ['highlights'] });
    };

    const handleRemoveHighlight = async () => {
        // Would need highlight IDs — for now reload to clear
        onClose();
        router.reload();
    };

    const handlePin = async () => {
        await apiCall('POST', '/api/v1/pins', {
            book_osis_id: bookOsis,
            chapter_number: chapter,
            verse_start: verseStart,
            verse_end: verseEnd > verseStart ? verseEnd : null,
            module_key: moduleKey,
            label: reference,
        });
        onClose();
    };

    const handleCopy = () => {
        navigator.clipboard.writeText(reference);
        onClose();
    };

    return (
        <div
            className="fixed z-50 bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 w-72"
            style={{ top: position.top, left: Math.min(position.left, window.innerWidth - 300) }}
        >
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100 dark:border-gray-800">
                <span className="text-sm font-medium text-gray-900 dark:text-white">{reference}</span>
                <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {/* Main actions */}
            {!showHighlightPicker && !showBookmarkForm && !showNoteForm && (
                <div className="p-2 space-y-0.5">
                    <ActionButton icon="🎨" label="Highlight" onClick={() => setShowHighlightPicker(true)} shortcut="H" />
                    <ActionButton icon="🔖" label="Bookmark" onClick={() => setShowBookmarkForm(true)} shortcut="B" />
                    <ActionButton icon="📝" label="Note" onClick={() => setShowNoteForm(true)} shortcut="N" />
                    <ActionButton icon="📌" label="Pin" onClick={handlePin} disabled={saving} shortcut="P" />
                    <ActionButton icon="📋" label="Copy reference" onClick={handleCopy} shortcut="C" />
                    <ActionButton icon="🖼️" label="Share as image" onClick={() => onShareImage?.(reference, reference)} shortcut="I" />
                </div>
            )}

            {/* Highlight color picker */}
            {showHighlightPicker && (
                <div className="p-3">
                    <div className="flex items-center justify-between mb-3">
                        <button onClick={() => setShowHighlightPicker(false)} className="text-gray-400 hover:text-gray-600">
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" strokeWidth={2} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Select Color</span>
                        <div className="w-4" />
                    </div>
                    <div className="grid grid-cols-4 gap-2">
                        {HIGHLIGHT_COLORS.map(c => (
                            <button
                                key={c.key}
                                onClick={() => handleHighlight(c.key)}
                                disabled={saving}
                                className={`h-8 w-full rounded-lg ${c.bg} hover:scale-110 transition-transform border-2 ${
                                    existingHighlight === c.key ? 'border-gray-900 dark:border-white' : 'border-transparent'
                                }`}
                                title={c.label}
                            />
                        ))}
                    </div>
                    {existingHighlight && (
                        <button
                            onClick={handleRemoveHighlight}
                            className="mt-2 w-full text-center text-xs text-red-500 hover:text-red-600"
                        >
                            Remove highlight
                        </button>
                    )}
                </div>
            )}

            {/* Bookmark form */}
            {showBookmarkForm && (
                <BookmarkForm
                    bookOsis={bookOsis}
                    chapter={chapter}
                    verseStart={verseStart}
                    verseEnd={verseEnd}
                    moduleKey={moduleKey}
                    reference={reference}
                    onClose={() => { setShowBookmarkForm(false); onClose(); }}
                    onBack={() => setShowBookmarkForm(false)}
                />
            )}

            {/* Note form */}
            {showNoteForm && (
                <NoteForm
                    bookOsis={bookOsis}
                    chapter={chapter}
                    verseStart={verseStart}
                    verseEnd={verseEnd}
                    moduleKey={moduleKey}
                    onClose={() => { setShowNoteForm(false); onClose(); }}
                    onBack={() => setShowNoteForm(false)}
                />
            )}
        </div>
    );
}

// ── Action Button ───────────────────────────────────────────────

function ActionButton({ icon, label, onClick, shortcut, disabled }: {
    icon: string;
    label: string;
    onClick: () => void;
    shortcut?: string;
    disabled?: boolean;
}) {
    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-50"
        >
            <span>{icon}</span>
            <span className="flex-1 text-left">{label}</span>
            {shortcut && (
                <kbd className="text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                    {shortcut}
                </kbd>
            )}
        </button>
    );
}

// ── Bookmark Form ───────────────────────────────────────────────

function BookmarkForm({ bookOsis, chapter, verseStart, verseEnd, moduleKey, reference, onClose, onBack }: {
    bookOsis: string;
    chapter: number;
    verseStart: number;
    verseEnd: number;
    moduleKey: string;
    reference: string;
    onClose: () => void;
    onBack: () => void;
}) {
    const [label, setLabel] = useState(reference);
    const [description, setDescription] = useState('');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch('/api/v1/bookmarks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                book_osis_id: bookOsis,
                chapter_number: chapter,
                verse_start: verseStart,
                verse_end: verseEnd > verseStart ? verseEnd : verseStart,
                module_key: moduleKey,
                label,
                description,
            }),
        });
        setSaving(false);
        onClose();
    };

    return (
        <div className="p-3 space-y-3">
            <div className="flex items-center gap-2">
                <button onClick={onBack} className="text-gray-400 hover:text-gray-600">
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Add Bookmark</span>
            </div>
            <input
                value={label}
                onChange={e => setLabel(e.target.value)}
                placeholder="Label"
                className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            <textarea
                value={description}
                onChange={e => setDescription(e.target.value)}
                placeholder="Description (optional)"
                rows={2}
                className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
            />
            <div className="flex gap-2">
                <button onClick={onBack} className="flex-1 text-sm py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                    Cancel
                </button>
                <button
                    onClick={save}
                    disabled={saving}
                    className="flex-1 text-sm py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save'}
                </button>
            </div>
        </div>
    );
}

// ── Note Form ───────────────────────────────────────────────────

function NoteForm({ bookOsis, chapter, verseStart, verseEnd, moduleKey, onClose, onBack }: {
    bookOsis: string;
    chapter: number;
    verseStart: number;
    verseEnd: number;
    moduleKey: string;
    onClose: () => void;
    onBack: () => void;
}) {
    const [title, setTitle] = useState('');
    const [content, setContent] = useState('');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch('/api/v1/notes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                book_osis_id: bookOsis,
                chapter_number: chapter,
                verse_start: verseStart,
                verse_end: verseEnd > verseStart ? verseEnd : null,
                module_key: moduleKey,
                title,
                content,
                content_format: 'markdown',
            }),
        });
        setSaving(false);
        onClose();
        router.reload({ only: ['notes'] });
    };

    return (
        <div className="p-3 space-y-3">
            <div className="flex items-center gap-2">
                <button onClick={onBack} className="text-gray-400 hover:text-gray-600">
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Add Note</span>
            </div>
            <input
                value={title}
                onChange={e => setTitle(e.target.value)}
                placeholder="Title"
                className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            <textarea
                value={content}
                onChange={e => setContent(e.target.value)}
                placeholder="Write your note... (Markdown supported)"
                rows={5}
                className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
            />
            <div className="flex gap-2">
                <button onClick={onBack} className="flex-1 text-sm py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                    Cancel
                </button>
                <button
                    onClick={save}
                    disabled={saving || !title}
                    className="flex-1 text-sm py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save'}
                </button>
            </div>
        </div>
    );
}
