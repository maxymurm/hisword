import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import AudioPlayer from '@/components/AudioPlayer';
import CommentaryPanel from '@/components/CommentaryPanel';
import DictionaryPopup from '@/components/DictionaryPopup';
import VerseActionPopover from '@/components/VerseActionPopover';
import VerseImageModal from '@/components/VerseImageModal';
import { useSwipe } from '@/hooks/useSwipe';
import type { PageProps } from '@/types';

// ── Types ───────────────────────────────────────────────────────

interface BibleModule {
    id: string;
    key: string;
    name: string;
    language: string;
    description?: string;
    engine?: string;
}

interface BookInfo {
    id: string;
    osis_id: string;
    name: string;
    abbreviation: string;
    testament: string;
    book_order: number;
    chapter_count: number;
}

interface VerseData {
    number: number;
    text: string;
    strongs?: unknown[];
    footnotes?: unknown[];
    cross_refs?: unknown[];
}

interface HighlightData {
    verse: number;
    color: string;
}

interface NoteData {
    id: string;
    verse_start: number;
    verse_end?: number;
    title: string;
}

interface NavLink {
    url: string;
    label: string;
}

interface ReaderProps extends PageProps {
    moduleKey: string;
    modules: BibleModule[];
    books: BookInfo[];
    currentBook: {
        osis_id: string;
        name: string;
        abbreviation: string;
        testament: string;
        chapter_count: number;
    } | null;
    chapterNumber: number;
    totalChapters: number;
    verses: VerseData[];
    prevLink: NavLink | null;
    nextLink: NavLink | null;
    highlights: HighlightData[];
    notes: NoteData[];
    commentaryModules: BibleModule[];
    dictionaryModules: BibleModule[];
}

// ── Highlight color map ─────────────────────────────────────────

const highlightColors: Record<string, string> = {
    yellow: 'bg-yellow-200/60 dark:bg-yellow-800/40',
    green: 'bg-green-200/60 dark:bg-green-800/40',
    blue: 'bg-blue-200/60 dark:bg-blue-800/40',
    pink: 'bg-pink-200/60 dark:bg-pink-800/40',
    purple: 'bg-purple-200/60 dark:bg-purple-800/40',
    orange: 'bg-orange-200/60 dark:bg-orange-800/40',
    red: 'bg-red-200/60 dark:bg-red-800/40',
    teal: 'bg-teal-200/60 dark:bg-teal-800/40',
};

// ── Font sizes ──────────────────────────────────────────────────

const fontSizes = [14, 16, 18, 20, 22, 24, 28];

// ── Main Component ──────────────────────────────────────────────

export default function Reader() {
    const props = usePage<ReaderProps>().props;
    const {
        moduleKey, modules, books, currentBook,
        chapterNumber, totalChapters, verses,
        prevLink, nextLink,
        highlights, notes,
        commentaryModules, dictionaryModules,
    } = props;

    const currentModule = modules.find(m => m.key === moduleKey);
    const isBintex = currentModule?.engine === 'bintex';
    const hasCommentary = !isBintex && commentaryModules.length > 0;

    // Local state
    const [fontSize, setFontSize] = useState(() => {
        if (typeof window !== 'undefined') {
            return parseInt(localStorage.getItem('reader_font_size') || '18', 10);
        }
        return 18;
    });
    const [paragraphMode, setParagraphMode] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('reader_paragraph_mode') === 'true';
        }
        return false;
    });
    const [selectedVerses, setSelectedVerses] = useState<Set<number>>(new Set());
    const [showBookSelector, setShowBookSelector] = useState(false);
    const [showModuleSelector, setShowModuleSelector] = useState(false);
    const [showCommentary, setShowCommentary] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('reader_show_commentary') === 'true';
        }
        return false;
    });
    const [dictLookup, setDictLookup] = useState<{ key: string; module: string; pos: { top: number; left: number } } | null>(null);
    const [commentaryActiveVerse, setCommentaryActiveVerse] = useState<number | null>(null);
    const [showSettings, setShowSettings] = useState(false);
    const [popoverPos, setPopoverPos] = useState<{ top: number; left: number } | null>(null);
    const [audioHighlightVerse, setAudioHighlightVerse] = useState<number | null>(null);
    const [shareImageData, setShareImageData] = useState<{ text: string; ref: string } | null>(null);
    const [showStrongs, setShowStrongs] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('reader_show_strongs') !== 'false';
        }
        return true;
    });
    const [showMorph, setShowMorph] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('reader_show_morph') === 'true';
        }
        return false;
    });
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [parallelModule, setParallelModule] = useState<string | null>(null);
    const [parallelVerses, setParallelVerses] = useState<VerseData[]>([]);
    const [showParallelSelector, setShowParallelSelector] = useState(false);
    const contentRef = useRef<HTMLDivElement>(null);

    // Swipe gestures for chapter navigation on mobile
    const swipeRef = useSwipe<HTMLDivElement>({
        onSwipeLeft: () => { if (nextLink) router.visit(nextLink.url, { preserveScroll: false }); },
        onSwipeRight: () => { if (prevLink) router.visit(prevLink.url, { preserveScroll: false }); },
        threshold: 80,
        enabled: true,
    });

    // Combine refs for the content div
    const setContentRef = useCallback((el: HTMLDivElement | null) => {
        (contentRef as React.MutableRefObject<HTMLDivElement | null>).current = el;
        (swipeRef as React.MutableRefObject<HTMLDivElement | null>).current = el;
    }, [swipeRef]);

    // Persist font size & paragraph mode
    useEffect(() => {
        localStorage.setItem('reader_font_size', String(fontSize));
    }, [fontSize]);
    useEffect(() => {
        localStorage.setItem('reader_paragraph_mode', String(paragraphMode));
    }, [paragraphMode]);
    useEffect(() => {
        localStorage.setItem('reader_show_commentary', String(showCommentary));
    }, [showCommentary]);
    useEffect(() => {
        localStorage.setItem('reader_show_strongs', String(showStrongs));
    }, [showStrongs]);
    useEffect(() => {
        localStorage.setItem('reader_show_morph', String(showMorph));
    }, [showMorph]);

    // Fullscreen change listener
    useEffect(() => {
        const handler = () => setIsFullscreen(!!document.fullscreenElement);
        document.addEventListener('fullscreenchange', handler);
        return () => document.removeEventListener('fullscreenchange', handler);
    }, []);

    const toggleFullscreen = useCallback(() => {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            document.documentElement.requestFullscreen();
        }
    }, []);

    // Fetch parallel verses when parallel module is selected
    useEffect(() => {
        if (!parallelModule || !currentBook) {
            setParallelVerses([]);
            return;
        }
        let cancelled = false;
        fetch(`/api/read/${parallelModule}/${currentBook.osis_id}/${chapterNumber}`)
            .then(r => r.json())
            .then(data => { if (!cancelled) setParallelVerses(data.verses || []); })
            .catch(() => { if (!cancelled) setParallelVerses([]); });
        return () => { cancelled = true; };
    }, [parallelModule, currentBook?.osis_id, chapterNumber]);

    // Strong's number click handler — intercepts clicks on <a> tags with lemma/strongs data
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            const target = e.target as HTMLElement;
            const strongsEl = target.closest('[data-strongs], a.strongs, .strongs-number');
            if (!strongsEl) return;

            e.preventDefault();
            e.stopPropagation();

            // Extract Strong's number from data attribute, class, href, or text
            let strongsKey = (strongsEl as HTMLElement).dataset?.strongs
                || (strongsEl as HTMLAnchorElement).href?.match(/strongs:([GH]\d+)/i)?.[1]
                || strongsEl.textContent?.trim() || '';

            // Normalize: ensure format like "H1234" or "G5678"
            strongsKey = strongsKey.replace(/^strongs:/i, '').trim();
            if (!strongsKey) return;

            // Determine which dictionary module to use
            const isHebrew = strongsKey.toUpperCase().startsWith('H');
            const hebrewMod = dictionaryModules.find(m => m.key.toLowerCase().includes('hebrew'));
            const greekMod = dictionaryModules.find(m => m.key.toLowerCase().includes('greek'));
            const dictModule = isHebrew
                ? (hebrewMod?.key || dictionaryModules[0]?.key || '')
                : (greekMod?.key || dictionaryModules[0]?.key || '');

            if (!dictModule) return;

            const rect = (strongsEl as HTMLElement).getBoundingClientRect();
            setDictLookup({
                key: strongsKey,
                module: dictModule,
                pos: { top: rect.bottom + 4, left: rect.left },
            });
        };

        const contentEl = contentRef.current;
        if (contentEl) {
            contentEl.addEventListener('click', handler);
            return () => contentEl.removeEventListener('click', handler);
        }
    }, [dictionaryModules]);

    // Scroll to top on chapter change
    useEffect(() => {
        contentRef.current?.scrollTo(0, 0);
    }, [moduleKey, currentBook?.osis_id, chapterNumber]);

    // Keyboard shortcuts
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
            if (e.key === 'ArrowLeft' && prevLink) {
                router.visit(prevLink.url, { preserveScroll: false });
            } else if (e.key === 'ArrowRight' && nextLink) {
                router.visit(nextLink.url, { preserveScroll: false });
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [prevLink, nextLink]);

    // Verse selection
    const handleVerseClick = useCallback((verseNum: number, e: React.MouseEvent) => {
        // Update commentary active verse for sync scrolling
        if (showCommentary) {
            setCommentaryActiveVerse(verseNum);
        }

        setSelectedVerses(prev => {
            const next = new Set(prev);
            if (e.shiftKey && prev.size > 0) {
                const anchor = Math.min(...prev);
                const [start, end] = verseNum > anchor
                    ? [anchor, verseNum]
                    : [verseNum, anchor];
                for (let i = start; i <= end; i++) next.add(i);
            } else {
                if (next.has(verseNum)) {
                    next.delete(verseNum);
                } else {
                    next.add(verseNum);
                }
            }
            if (next.size > 0) {
                const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
                setPopoverPos({ top: rect.bottom + 8, left: Math.min(rect.left, window.innerWidth - 320) });
            } else {
                setPopoverPos(null);
            }
            return next;
        });
    }, [showCommentary]);

    // Highlight lookup
    const highlightMap = new Map(highlights.map(h => [h.verse, h.color]));
    const noteMap = new Map(notes.map(n => [n.verse_start, n]));

    // Auto-advance to next chapter when audio ends
    const handleAudioChapterEnd = useCallback(() => {
        if (nextLink) router.visit(nextLink.url, { preserveScroll: false });
    }, [nextLink]);

    // Navigate helper
    const navigateTo = (mod: string, book: string, ch: number) => {
        router.visit(`/read/${mod}/${book}/${ch}`, { preserveScroll: false });
        setShowBookSelector(false);
        setShowModuleSelector(false);
    };

    return (
        <>
            <Head title={currentBook ? `${currentBook.name} ${chapterNumber} – ${moduleKey}` : 'Bible Reader'} />

            <div className={`flex ${isFullscreen ? 'h-screen' : 'h-[calc(100vh-3.5rem)]'} flex-col bg-gray-50 dark:bg-gray-950`}>
                {/* Toolbar */}
                <div className="flex-none border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                    <div className="mx-auto max-w-5xl flex items-center justify-between px-4 py-2 gap-2">
                        {/* Left: Home button + Book & Chapter selector */}
                        <div className="flex items-center gap-2">
                            <Link
                                href="/"
                                className="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                title="Home"
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                            </Link>

                            <button
                                onClick={() => { setShowBookSelector(!showBookSelector); setShowModuleSelector(false); setShowParallelSelector(false); }}
                                className="flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            >
                                <span className="text-gray-900 dark:text-white">
                                    {currentBook?.name ?? 'Select Book'} {chapterNumber}
                                </span>
                                <ChevronDown />
                            </button>

                            <button
                                onClick={() => { setShowModuleSelector(!showModuleSelector); setShowBookSelector(false); setShowParallelSelector(false); }}
                                className="flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                            >
                                {moduleKey}
                                <ChevronDown />
                            </button>
                        </div>

                        {/* Center: Chapter nav */}
                        <div className="hidden sm:flex items-center gap-1">
                            {prevLink && (
                                <Link href={prevLink.url} className="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title={prevLink.label}>
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                                </Link>
                            )}
                            <span className="px-2 text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                {chapterNumber} / {totalChapters}
                            </span>
                            {nextLink && (
                                <Link href={nextLink.url} className="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title={nextLink.label}>
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </Link>
                            )}
                        </div>

                        {/* Right: Settings */}
                        <div className="flex items-center gap-1">
                            {/* Strong's toggle (SWORD modules only) */}
                            {!isBintex && dictionaryModules.length > 0 && (
                                <button
                                    onClick={() => setShowStrongs(!showStrongs)}
                                    className={`rounded p-1.5 transition-colors ${showStrongs ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                    title={showStrongs ? 'Hide Strong\'s numbers' : 'Show Strong\'s numbers'}
                                >
                                    <span className="text-xs font-bold leading-none">S#</span>
                                </button>
                            )}

                            {/* Parallel reading */}
                            {modules.length > 1 && (
                                <button
                                    onClick={() => {
                                        if (parallelModule) {
                                            setParallelModule(null);
                                            setShowParallelSelector(false);
                                        } else {
                                            setShowParallelSelector(!showParallelSelector);
                                            setShowBookSelector(false);
                                            setShowModuleSelector(false);
                                        }
                                    }}
                                    className={`rounded p-1.5 transition-colors ${parallelModule ? 'text-cyan-600 dark:text-cyan-400 bg-cyan-50 dark:bg-cyan-900/20' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                    title={parallelModule ? `Parallel: ${parallelModule} (click to close)` : 'Compare translations'}
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                </button>
                            )}

                            <button
                                onClick={() => setFontSize(s => Math.max(14, s - 2))}
                                className="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                title="Decrease font size"
                            >
                                <span className="text-xs font-bold">A-</span>
                            </button>
                            <button
                                onClick={() => setFontSize(s => Math.min(28, s + 2))}
                                className="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                title="Increase font size"
                            >
                                <span className="text-sm font-bold">A+</span>
                            </button>
                            <button
                                onClick={() => setParagraphMode(!paragraphMode)}
                                className={`rounded p-1.5 transition-colors ${paragraphMode ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                title={paragraphMode ? 'Switch to verse mode' : 'Switch to paragraph mode'}
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h10.5" /></svg>
                            </button>
                            {hasCommentary && (
                                <button
                                    onClick={() => setShowCommentary(!showCommentary)}
                                    className={`rounded p-1.5 transition-colors ${showCommentary ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                    title={showCommentary ? 'Hide commentary' : 'Show commentary'}
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                    </svg>
                                </button>
                            )}

                            {/* Fullscreen toggle */}
                            <button
                                onClick={toggleFullscreen}
                                className="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                title={isFullscreen ? 'Exit fullscreen' : 'Fullscreen'}
                            >
                                {isFullscreen ? (
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
                                    </svg>
                                ) : (
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                    </svg>
                                )}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Dropdowns */}
                {showBookSelector && (
                    <BookSelector
                        books={books}
                        currentBookOsis={currentBook?.osis_id ?? ''}
                        currentChapter={chapterNumber}
                        moduleKey={moduleKey}
                        onSelect={(bookOsis, ch) => navigateTo(moduleKey, bookOsis, ch)}
                        onClose={() => setShowBookSelector(false)}
                    />
                )}
                {showModuleSelector && (
                    <ModuleSelector
                        modules={modules}
                        currentModule={moduleKey}
                        bookOsis={currentBook?.osis_id ?? 'Gen'}
                        chapter={chapterNumber}
                        onSelect={(mod) => navigateTo(mod, currentBook?.osis_id ?? 'Gen', chapterNumber)}
                        onClose={() => setShowModuleSelector(false)}
                    />
                )}
                {showParallelSelector && (
                    <div className="flex-none border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg max-h-60 overflow-y-auto">
                        <div className="mx-auto max-w-5xl px-4 py-3">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-sm font-medium text-gray-900 dark:text-white">Compare with Translation</span>
                                <button onClick={() => setShowParallelSelector(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                {modules.filter(m => m.key !== moduleKey).map(mod => (
                                    <button
                                        key={mod.key}
                                        onClick={() => { setParallelModule(mod.key); setShowParallelSelector(false); }}
                                        className={`flex flex-col p-3 rounded-lg text-left transition-colors ${
                                            mod.key === parallelModule
                                                ? 'bg-cyan-100 dark:bg-cyan-900/30 ring-2 ring-cyan-500'
                                                : 'bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700'
                                        }`}
                                    >
                                        <span className="text-sm font-semibold text-gray-900 dark:text-white">{mod.key}</span>
                                        <span className="text-xs text-gray-500 dark:text-gray-400 truncate">{mod.name}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Content + Commentary split layout */}
                <div className="flex flex-1 overflow-hidden">
                    {/* Main content */}
                    <div ref={setContentRef} className={`flex-1 overflow-y-auto pb-16 sm:pb-0 ${showCommentary ? 'lg:border-r lg:border-gray-200 lg:dark:border-gray-800' : ''} ${showStrongs ? '' : 'hide-strongs'}`}>
                        <div className={`mx-auto px-4 sm:px-8 py-8 ${parallelModule ? 'max-w-5xl' : 'max-w-3xl'}`}>
                        {/* Chapter heading */}
                        <h2 className="mb-6 text-center text-xl font-semibold text-gray-400 dark:text-gray-500">
                            {currentBook?.name} {chapterNumber}
                            {parallelModule && (
                                <span className="ml-2 text-sm text-cyan-500 dark:text-cyan-400">
                                    ({moduleKey} / {parallelModule})
                                </span>
                            )}
                        </h2>

                        {/* Verses */}
                        {verses.length > 0 ? (
                            <div className={paragraphMode ? '' : 'space-y-1'}>
                                {verses.map((verse) => {
                                    const hlColor = highlightMap.get(verse.number);
                                    const note = noteMap.get(verse.number);
                                    const isSelected = selectedVerses.has(verse.number);
                                    const isAudioActive = audioHighlightVerse === verse.number;
                                    const pVerse = parallelModule ? parallelVerses.find(v => v.number === verse.number) : null;

                                    return paragraphMode ? (
                                        <span
                                            key={verse.number}
                                            onClick={(e) => handleVerseClick(verse.number, e)}
                                            className={`
                                                cursor-pointer transition-colors
                                                ${isAudioActive ? 'bg-amber-100 dark:bg-amber-900/30 rounded' : ''}
                                                ${isSelected ? 'bg-indigo-100 dark:bg-indigo-900/30 rounded' : ''}
                                                ${hlColor ? highlightColors[hlColor] ?? '' : ''}
                                            `}
                                        >
                                            <sup className="mr-1 text-xs font-semibold text-indigo-500 dark:text-indigo-400 select-none">
                                                {verse.number}
                                            </sup>
                                            <span
                                                style={{ fontSize: `${fontSize}px`, lineHeight: '1.8' }}
                                                className="text-gray-900 dark:text-gray-100"
                                                dangerouslySetInnerHTML={{ __html: verse.text }}
                                            />
                                            {note && (
                                                <span className="inline-block ml-1 text-amber-500" title={note.title}>
                                                    📝
                                                </span>
                                            )}
                                            {' '}
                                        </span>
                                    ) : (
                                        <div
                                            key={verse.number}
                                            onClick={(e) => handleVerseClick(verse.number, e)}
                                            className={`
                                                flex gap-3 py-1 px-2 rounded-lg cursor-pointer transition-colors
                                                ${isAudioActive ? 'bg-amber-100 dark:bg-amber-900/30' : ''}
                                                ${isSelected ? 'bg-indigo-100 dark:bg-indigo-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-800/50'}
                                                ${hlColor ? highlightColors[hlColor] ?? '' : ''}
                                            `}
                                        >
                                            <span className="flex-none w-8 pt-0.5 text-right text-xs font-semibold text-indigo-500 dark:text-indigo-400 select-none tabular-nums">
                                                {verse.number}
                                            </span>
                                            <div className={`flex-1 ${parallelModule ? 'grid grid-cols-2 gap-4' : ''}`}>
                                                <span
                                                    style={{ fontSize: `${fontSize}px`, lineHeight: '1.8' }}
                                                    className="text-gray-900 dark:text-gray-100"
                                                    dangerouslySetInnerHTML={{ __html: verse.text }}
                                                />
                                                {parallelModule && pVerse && (
                                                    <span
                                                        style={{ fontSize: `${fontSize}px`, lineHeight: '1.8' }}
                                                        className="text-gray-700 dark:text-gray-300 border-l border-gray-200 dark:border-gray-700 pl-4"
                                                        dangerouslySetInnerHTML={{ __html: pVerse.text }}
                                                    />
                                                )}
                                            </div>
                                            {note && (
                                                <span className="flex-none text-amber-500" title={note.title}>
                                                    📝
                                                </span>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-20 text-gray-400 dark:text-gray-500">
                                <svg className="h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                                <p className="text-lg font-medium">No content available</p>
                                <p className="text-sm mt-1">This module may not have data for the selected passage.</p>
                            </div>
                        )}

                        {/* Bottom chapter nav (mobile friendly) */}
                        <div className="flex items-center justify-between mt-12 pt-6 border-t border-gray-200 dark:border-gray-800">
                            {prevLink ? (
                                <Link href={prevLink.url} className="flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 transition-colors">
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                                    {prevLink.label}
                                </Link>
                            ) : <div />}
                            {nextLink ? (
                                <Link href={nextLink.url} className="flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 transition-colors">
                                    {nextLink.label}
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </Link>
                            ) : <div />}
                        </div>
                    </div>
                </div>

                    {/* Commentary panel (desktop: side panel, mobile: bottom sheet) */}
                    {showCommentary && hasCommentary && (
                        <>
                            {/* Desktop side panel */}
                            <div className="hidden lg:flex w-96 flex-none flex-col overflow-hidden">
                                <CommentaryPanel
                                    moduleKey={commentaryModules[0].key}
                                    bookOsis={currentBook?.osis_id ?? 'Gen'}
                                    chapter={chapterNumber}
                                    availableModules={commentaryModules}
                                    onClose={() => setShowCommentary(false)}
                                    activeVerse={commentaryActiveVerse}
                                />
                            </div>

                            {/* Mobile bottom sheet */}
                            <div className="lg:hidden fixed inset-x-0 bottom-0 z-40 max-h-[50vh] flex flex-col rounded-t-2xl shadow-2xl border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                                <div className="flex-none flex justify-center py-2">
                                    <div className="w-10 h-1 bg-gray-300 dark:bg-gray-600 rounded-full" />
                                </div>
                                <div className="flex-1 overflow-hidden">
                                    <CommentaryPanel
                                        moduleKey={commentaryModules[0].key}
                                        bookOsis={currentBook?.osis_id ?? 'Gen'}
                                        chapter={chapterNumber}
                                        availableModules={commentaryModules}
                                        onClose={() => setShowCommentary(false)}
                                        activeVerse={commentaryActiveVerse}
                                    />
                                </div>
                            </div>
                        </>
                    )}
                </div>

                {/* Dictionary Popup */}
                {dictLookup && dictionaryModules.length > 0 && (
                    <DictionaryPopup
                        moduleKey={dictLookup.module}
                        lookupKey={dictLookup.key}
                        position={dictLookup.pos}
                        onClose={() => setDictLookup(null)}
                        availableModules={dictionaryModules}
                    />
                )}

                {/* Verse Action Popover */}
                {selectedVerses.size > 0 && popoverPos && currentBook && (
                    <VerseActionPopover
                        selectedVerses={[...selectedVerses].sort((a, b) => a - b)}
                        bookOsis={currentBook.osis_id}
                        chapter={chapterNumber}
                        moduleKey={moduleKey}
                        existingHighlight={
                            selectedVerses.size === 1
                                ? highlightMap.get([...selectedVerses][0]) ?? null
                                : null
                        }
                        position={popoverPos}
                        onClose={() => {
                            setSelectedVerses(new Set());
                            setPopoverPos(null);
                        }}
                        onShareImage={(verseText, ref) => {
                            setShareImageData({ text: verseText, ref });
                            setSelectedVerses(new Set());
                            setPopoverPos(null);
                        }}
                    />
                )}

                {/* Verse Image Modal */}
                {shareImageData && (
                    <VerseImageModal
                        verseText={shareImageData.text}
                        reference={shareImageData.ref}
                        onClose={() => setShareImageData(null)}
                    />
                )}

                {/* Audio Player */}
                {currentBook && (
                    <AudioPlayer
                        moduleKey={moduleKey}
                        bookOsis={currentBook.osis_id}
                        chapter={chapterNumber}
                        onVerseHighlight={setAudioHighlightVerse}
                        onChapterEnd={handleAudioChapterEnd}
                    />
                )}
            </div>
        </>
    );
}

// ── Book Selector ───────────────────────────────────────────────

function BookSelector({ books, currentBookOsis, currentChapter, moduleKey, onSelect, onClose }: {
    books: BookInfo[];
    currentBookOsis: string;
    currentChapter: number;
    moduleKey: string;
    onSelect: (bookOsis: string, chapter: number) => void;
    onClose: () => void;
}) {
    const [selectedBook, setSelectedBook] = useState<string | null>(null);
    const [tab, setTab] = useState<'ot' | 'nt'>(
        books.find(b => b.osis_id === currentBookOsis)?.testament === 'NT' ? 'nt' : 'ot'
    );

    const otBooks = books.filter(b => b.testament === 'OT');
    const ntBooks = books.filter(b => b.testament === 'NT');
    const displayBooks = tab === 'ot' ? otBooks : ntBooks;

    const bookForChapters = selectedBook ? books.find(b => b.osis_id === selectedBook) : null;

    return (
        <div className="flex-none border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg max-h-80 overflow-y-auto">
            <div className="mx-auto max-w-5xl px-4 py-3">
                {!selectedBook ? (
                    <>
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex gap-1">
                                <button
                                    onClick={() => setTab('ot')}
                                    className={`px-3 py-1 text-sm font-medium rounded-lg transition-colors ${tab === 'ot' ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                >
                                    Old Testament
                                </button>
                                <button
                                    onClick={() => setTab('nt')}
                                    className={`px-3 py-1 text-sm font-medium rounded-lg transition-colors ${tab === 'nt' ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                >
                                    New Testament
                                </button>
                            </div>
                            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div className="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-1">
                            {displayBooks.map(book => (
                                <button
                                    key={book.osis_id}
                                    onClick={() => book.chapter_count === 1 ? onSelect(book.osis_id, 1) : setSelectedBook(book.osis_id)}
                                    className={`px-2 py-1.5 text-xs font-medium rounded transition-colors truncate ${
                                        book.osis_id === currentBookOsis
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30'
                                    }`}
                                    title={book.name}
                                >
                                    {book.abbreviation}
                                </button>
                            ))}
                        </div>
                    </>
                ) : (
                    <>
                        <div className="flex items-center gap-2 mb-3">
                            <button onClick={() => setSelectedBook(null)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                            </button>
                            <span className="text-sm font-medium text-gray-900 dark:text-white">
                                {bookForChapters?.name} — Select Chapter
                            </span>
                        </div>
                        <div className="grid grid-cols-8 sm:grid-cols-10 md:grid-cols-12 gap-1">
                            {bookForChapters && Array.from({ length: bookForChapters.chapter_count }, (_, i) => i + 1).map(ch => (
                                <button
                                    key={ch}
                                    onClick={() => onSelect(selectedBook, ch)}
                                    className={`px-2 py-1.5 text-sm font-medium rounded tabular-nums transition-colors ${
                                        selectedBook === currentBookOsis && ch === currentChapter
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30'
                                    }`}
                                >
                                    {ch}
                                </button>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

// ── Module Selector ─────────────────────────────────────────────

function ModuleSelector({ modules, currentModule, bookOsis, chapter, onSelect, onClose }: {
    modules: BibleModule[];
    currentModule: string;
    bookOsis: string;
    chapter: number;
    onSelect: (moduleKey: string) => void;
    onClose: () => void;
}) {
    return (
        <div className="flex-none border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg max-h-60 overflow-y-auto">
            <div className="mx-auto max-w-5xl px-4 py-3">
                <div className="flex items-center justify-between mb-3">
                    <span className="text-sm font-medium text-gray-900 dark:text-white">Select Translation</span>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    {modules.map(mod => (
                        <button
                            key={mod.key}
                            onClick={() => onSelect(mod.key)}
                            className={`flex flex-col p-3 rounded-lg text-left transition-colors ${
                                mod.key === currentModule
                                    ? 'bg-indigo-100 dark:bg-indigo-900/30 ring-2 ring-indigo-500'
                                    : 'bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700'
                            }`}
                        >
                            <div className="flex items-center gap-1.5">
                                <span className="text-sm font-semibold text-gray-900 dark:text-white">{mod.key}</span>
                                {mod.engine === 'bintex' && (
                                    <span className="px-1 py-0.5 text-[10px] font-medium rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">YES2</span>
                                )}
                            </div>
                            <span className="text-xs text-gray-500 dark:text-gray-400 truncate">{mod.name}</span>
                        </button>
                    ))}
                </div>
                {modules.length === 0 && (
                    <p className="text-center text-sm text-gray-400 py-4">No Bible modules installed</p>
                )}
            </div>
        </div>
    );
}

// ── Icons ───────────────────────────────────────────────────────

function ChevronDown() {
    return (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    );
}
