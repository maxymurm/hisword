import { useCallback, useRef, useState } from 'react';

// ── Types ───────────────────────────────────────────────────────

type Template = 'minimal' | 'nature' | 'gradient' | 'dark' | 'watercolor';
type AspectRatio = '1:1' | '9:16' | '16:9';
type FontFamily = 'serif' | 'sans' | 'mono';

interface VerseImageModalProps {
    verseText: string;
    reference: string;
    onClose: () => void;
}

const TEMPLATES: { id: Template; name: string; colors: [string, string] }[] = [
    { id: 'minimal', name: 'Minimal', colors: ['#ffffff', '#f8fafc'] },
    { id: 'nature', name: 'Nature', colors: ['#2d5016', '#4a7c59'] },
    { id: 'gradient', name: 'Gradient', colors: ['#4f46e5', '#7c3aed'] },
    { id: 'dark', name: 'Dark', colors: ['#0f172a', '#1e293b'] },
    { id: 'watercolor', name: 'Watercolor', colors: ['#fef3c7', '#f59e0b'] },
];

const ASPECT_RATIOS: { value: AspectRatio; label: string; icon: string }[] = [
    { value: '1:1', label: 'Square', icon: '⬜' },
    { value: '9:16', label: 'Story', icon: '📱' },
    { value: '16:9', label: 'Wide', icon: '🖥️' },
];

const FONTS: { value: FontFamily; label: string }[] = [
    { value: 'serif', label: 'Serif' },
    { value: 'sans', label: 'Sans' },
    { value: 'mono', label: 'Mono' },
];

// ── Component ───────────────────────────────────────────────────

export default function VerseImageModal({ verseText, reference, onClose }: VerseImageModalProps) {
    const [template, setTemplate] = useState<Template>('gradient');
    const [aspectRatio, setAspectRatio] = useState<AspectRatio>('1:1');
    const [fontFamily, setFontFamily] = useState<FontFamily>('serif');
    const [fontSize, setFontSize] = useState(32);
    const [watermark, setWatermark] = useState('');
    const [generating, setGenerating] = useState(false);
    const [imageData, setImageData] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);

    // Generate image via server
    const generateImage = useCallback(async () => {
        setGenerating(true);
        setError(null);

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const token = csrfMeta?.getAttribute('content') ?? '';

            const res = await fetch('/verse-image/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    verse_text: verseText,
                    reference,
                    template,
                    aspect_ratio: aspectRatio,
                    font_size: fontSize,
                    font_family: fontFamily,
                    watermark: watermark || null,
                    retina: true,
                }),
            });

            if (!res.ok) throw new Error('Generation failed');
            const data = await res.json();
            setImageData(data.image);
        } catch {
            setError('Failed to generate image. Please try again.');
        } finally {
            setGenerating(false);
        }
    }, [verseText, reference, template, aspectRatio, fontSize, fontFamily, watermark]);

    // Download image
    const downloadImage = useCallback(() => {
        if (!imageData) return;
        const link = document.createElement('a');
        link.download = `${reference.replace(/\s+/g, '_')}.png`;
        link.href = imageData;
        link.click();
    }, [imageData, reference]);

    // Share via Web Share API
    const shareImage = useCallback(async () => {
        if (!imageData) return;
        try {
            const blob = await (await fetch(imageData)).blob();
            const file = new File([blob], `${reference}.png`, { type: 'image/png' });

            if (navigator.share && navigator.canShare({ files: [file] })) {
                await navigator.share({
                    title: reference,
                    text: verseText,
                    files: [file],
                });
            } else {
                downloadImage();
            }
        } catch {
            downloadImage();
        }
    }, [imageData, reference, verseText, downloadImage]);

    // Copy to clipboard
    const copyImage = useCallback(async () => {
        if (!imageData) return;
        try {
            const blob = await (await fetch(imageData)).blob();
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': blob }),
            ]);
        } catch {
            // Fallback: download
            downloadImage();
        }
    }, [imageData, downloadImage]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Create Verse Image</h2>
                    <button onClick={onClose} className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="p-4 space-y-4">
                    {/* Preview */}
                    {imageData ? (
                        <div className="flex justify-center">
                            <img
                                src={imageData}
                                alt="Verse preview"
                                className="max-w-full max-h-80 rounded-lg shadow-lg"
                            />
                        </div>
                    ) : (
                        <div className={`
                            rounded-lg p-8 flex flex-col items-center justify-center text-center min-h-[200px]
                            ${template === 'minimal' ? 'bg-white border border-gray-200' : ''}
                            ${template === 'nature' ? 'bg-gradient-to-b from-green-800 to-green-600 text-white' : ''}
                            ${template === 'gradient' ? 'bg-gradient-to-b from-indigo-600 to-purple-600 text-white' : ''}
                            ${template === 'dark' ? 'bg-gradient-to-b from-slate-900 to-slate-800 text-slate-200' : ''}
                            ${template === 'watercolor' ? 'bg-gradient-to-b from-amber-100 to-amber-400 text-amber-900' : ''}
                        `}>
                            <p className={`text-lg italic leading-relaxed mb-3 ${fontFamily === 'serif' ? 'font-serif' : fontFamily === 'mono' ? 'font-mono' : 'font-sans'}`} style={{ fontSize: `${Math.min(fontSize, 24)}px` }}>
                                "{verseText.substring(0, 150)}{verseText.length > 150 ? '…' : ''}"
                            </p>
                            <p className="text-sm opacity-70">— {reference}</p>
                        </div>
                    )}

                    {/* Template Selector */}
                    <div>
                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Template</label>
                        <div className="flex gap-2">
                            {TEMPLATES.map(t => (
                                <button
                                    key={t.id}
                                    onClick={() => { setTemplate(t.id); setImageData(null); }}
                                    className={`flex-1 py-2 px-3 rounded-lg text-xs font-medium transition-all ${
                                        template === t.id
                                            ? 'ring-2 ring-indigo-500 ring-offset-2 dark:ring-offset-gray-900'
                                            : 'hover:ring-1 hover:ring-gray-300'
                                    }`}
                                    style={{
                                        background: `linear-gradient(135deg, ${t.colors[0]}, ${t.colors[1]})`,
                                        color: ['minimal', 'watercolor'].includes(t.id) ? '#1a1a2e' : '#fff',
                                    }}
                                >
                                    {t.name}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Aspect Ratio */}
                    <div>
                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Aspect Ratio</label>
                        <div className="flex gap-2">
                            {ASPECT_RATIOS.map(ar => (
                                <button
                                    key={ar.value}
                                    onClick={() => { setAspectRatio(ar.value); setImageData(null); }}
                                    className={`flex-1 py-2 px-3 rounded-lg text-sm transition-colors ${
                                        aspectRatio === ar.value
                                            ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-500'
                                            : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                                    }`}
                                >
                                    {ar.icon} {ar.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Font & Size */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Font</label>
                            <div className="flex gap-1">
                                {FONTS.map(f => (
                                    <button
                                        key={f.value}
                                        onClick={() => { setFontFamily(f.value); setImageData(null); }}
                                        className={`flex-1 py-1.5 rounded text-xs transition-colors ${
                                            fontFamily === f.value
                                                ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                        } ${f.value === 'serif' ? 'font-serif' : f.value === 'mono' ? 'font-mono' : 'font-sans'}`}
                                    >
                                        {f.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Font Size: {fontSize}px</label>
                            <input
                                type="range"
                                min={16}
                                max={72}
                                step={2}
                                value={fontSize}
                                onChange={e => { setFontSize(parseInt(e.target.value)); setImageData(null); }}
                                className="w-full accent-indigo-600"
                            />
                        </div>
                    </div>

                    {/* Watermark */}
                    <div>
                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Watermark (optional)</label>
                        <input
                            type="text"
                            value={watermark}
                            onChange={e => { setWatermark(e.target.value); setImageData(null); }}
                            placeholder="e.g., HisWord"
                            maxLength={50}
                            className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>

                    {error && (
                        <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
                    )}

                    {/* Actions */}
                    <div className="flex gap-2 pt-2">
                        {!imageData ? (
                            <button
                                onClick={generateImage}
                                disabled={generating}
                                className="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2"
                            >
                                {generating ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Generating…
                                    </>
                                ) : (
                                    'Generate Image'
                                )}
                            </button>
                        ) : (
                            <>
                                <button
                                    onClick={downloadImage}
                                    className="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Download
                                </button>
                                <button
                                    onClick={shareImage}
                                    className="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors flex items-center justify-center gap-2"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                                    </svg>
                                    Share
                                </button>
                                <button
                                    onClick={copyImage}
                                    className="py-2.5 px-4 rounded-lg text-sm font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                    title="Copy to clipboard"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                    </svg>
                                </button>
                                <button
                                    onClick={() => setImageData(null)}
                                    className="py-2.5 px-4 rounded-lg text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                                    title="Edit settings"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                    </svg>
                                </button>
                            </>
                        )}
                    </div>
                </div>

                <canvas ref={canvasRef} className="hidden" />
            </div>
        </div>
    );
}
