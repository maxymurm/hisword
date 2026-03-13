import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface PinData {
    id: string;
    book_osis_id: string;
    chapter_number: number;
    verse_number: number;
    module_key: string;
    label: string | null;
    updated_at: string;
}

interface Props {
    pins: PinData[];
}

export default function Pins({ pins }: Props) {
    const removePin = async (id: string) => {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch(`/api/v1/pins/${id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
            credentials: 'same-origin',
        });
        router.reload();
    };

    return (
        <AppLayout title="Pins">
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Pinned Verses</h1>
                    <span className="text-sm text-gray-500 dark:text-gray-400">{pins.length} pins</span>
                </div>

                {pins.length > 0 ? (
                    <div className="space-y-2">
                        {pins.map(pin => (
                            <div
                                key={pin.id}
                                className="flex items-center gap-4 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-4 py-3 shadow-sm hover:shadow-md transition-shadow"
                            >
                                <span className="flex-none text-lg">📌</span>
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={`/read/${pin.module_key}/${pin.book_osis_id}/${pin.chapter_number}`}
                                        className="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                    >
                                        {pin.book_osis_id} {pin.chapter_number}:{pin.verse_number}
                                    </Link>
                                    {pin.label && (
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {pin.label}
                                        </p>
                                    )}
                                </div>
                                <span className="flex-none text-xs text-gray-400 dark:text-gray-500">
                                    {pin.module_key}
                                </span>
                                <button
                                    onClick={() => removePin(pin.id)}
                                    className="flex-none text-gray-400 hover:text-red-500 transition-colors"
                                    title="Unpin"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <span className="text-4xl mb-4 block">📌</span>
                        <p className="text-lg font-medium">No pinned verses</p>
                        <p className="text-sm mt-1">Pin verses in the reader for quick access</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
