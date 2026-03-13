import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Tag {
    id: string;
    name: string;
    color: string;
    description: string | null;
    bookmarks_count: number;
    notes_count: number;
    highlights_count: number;
}

interface AnnotationItem {
    id: string;
    book_osis_id: string;
    chapter_number: number;
    verse_start?: number;
    verse_number?: number;
    label?: string;
    title?: string;
    content?: string;
    color?: string;
    module_key: string;
    created_at: string;
}

interface Props {
    tag: Tag;
    bookmarks: AnnotationItem[];
    notes: AnnotationItem[];
    highlights: AnnotationItem[];
}

export default function TagShow({ tag, bookmarks, notes, highlights }: Props) {
    return (
        <AppLayout title={`Tag: ${tag.name}`}>
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <Link href="/tags" className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline mb-4 inline-block">
                    ← All Tags
                </Link>

                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">{tag.name}</h1>
                    {tag.description && (
                        <p className="text-gray-500 dark:text-gray-400">{tag.description}</p>
                    )}
                    <div className="mt-3 flex gap-4 text-sm text-gray-500">
                        <span>📑 {tag.bookmarks_count} bookmarks</span>
                        <span>📝 {tag.notes_count} notes</span>
                        <span>🖍️ {tag.highlights_count} highlights</span>
                    </div>
                </div>

                {/* Bookmarks */}
                {bookmarks.length > 0 && (
                    <Section title="Bookmarks">
                        {bookmarks.map(b => (
                            <ItemCard
                                key={b.id}
                                reference={`${b.book_osis_id} ${b.chapter_number}:${b.verse_start}`}
                                title={b.label}
                                module={b.module_key}
                            />
                        ))}
                    </Section>
                )}

                {/* Notes */}
                {notes.length > 0 && (
                    <Section title="Notes">
                        {notes.map(n => (
                            <ItemCard
                                key={n.id}
                                reference={`${n.book_osis_id} ${n.chapter_number}:${n.verse_start}`}
                                title={n.title}
                                subtitle={n.content?.substring(0, 100)}
                                module={n.module_key}
                            />
                        ))}
                    </Section>
                )}

                {/* Highlights */}
                {highlights.length > 0 && (
                    <Section title="Highlights">
                        {highlights.map(h => (
                            <ItemCard
                                key={h.id}
                                reference={`${h.book_osis_id} ${h.chapter_number}:${h.verse_number}`}
                                module={h.module_key}
                                color={h.color}
                            />
                        ))}
                    </Section>
                )}

                {bookmarks.length === 0 && notes.length === 0 && highlights.length === 0 && (
                    <div className="text-center py-16">
                        <div className="text-4xl mb-3">📭</div>
                        <p className="text-gray-500 dark:text-gray-400">No items tagged yet.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="mb-8">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-3">{title}</h2>
            <div className="space-y-2">{children}</div>
        </div>
    );
}

function ItemCard({ reference, title, subtitle, module, color }: {
    reference: string;
    title?: string | null;
    subtitle?: string | null;
    module: string;
    color?: string | null;
}) {
    const COLORS: Record<string, string> = {
        yellow: 'border-l-yellow-400',
        green: 'border-l-green-400',
        blue: 'border-l-blue-400',
        pink: 'border-l-pink-400',
        purple: 'border-l-purple-400',
        orange: 'border-l-orange-400',
    };

    return (
        <div className={`rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3 ${color ? `border-l-4 ${COLORS[color] || ''}` : ''}`}>
            <div className="flex items-center justify-between">
                <div>
                    <span className="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{reference}</span>
                    {title && <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">{title}</span>}
                </div>
                <span className="text-xs text-gray-400 font-mono">{module}</span>
            </div>
            {subtitle && <p className="text-xs text-gray-500 mt-1 truncate">{subtitle}</p>}
        </div>
    );
}
