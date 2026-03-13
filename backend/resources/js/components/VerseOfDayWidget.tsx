import { useState, useEffect } from 'react';

interface VerseOfDay {
    ref: string;
    text: string;
}

export default function VerseOfDayWidget() {
    const [verse, setVerse] = useState<VerseOfDay | null>(null);

    useEffect(() => {
        fetch('/verse-of-day')
            .then(r => r.json())
            .then(data => setVerse(data.verse))
            .catch(() => {});
    }, []);

    if (!verse) return null;

    return (
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-700 p-6 text-white shadow-lg">
            <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-8 translate-x-8" />
            <div className="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-6 -translate-x-6" />
            <div className="relative">
                <div className="text-xs uppercase tracking-wider text-indigo-200 mb-3 font-semibold">Verse of the Day</div>
                <blockquote className="text-lg leading-relaxed mb-4 font-serif italic">
                    &ldquo;{verse.text}&rdquo;
                </blockquote>
                <div className="flex items-center justify-between">
                    <span className="font-semibold text-indigo-100">{verse.ref}</span>
                    <button
                        onClick={() => navigator.clipboard?.writeText(`${verse.text} — ${verse.ref}`)}
                        className="text-xs px-3 py-1 rounded-full bg-white/20 hover:bg-white/30 transition"
                    >
                        Copy
                    </button>
                </div>
            </div>
        </div>
    );
}
