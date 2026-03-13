import { useCallback, useEffect, useRef, useState } from 'react';

// ── Types ───────────────────────────────────────────────────────

interface VerseTiming {
    verse: number;
    start: number;
    end: number;
}

interface AudioData {
    id: string;
    stream_url: string;
    duration: number | null;
    formatted_duration: string;
    format: string;
    narrator: string | null;
    verse_timings: VerseTiming[] | null;
}

interface AudioPlayerProps {
    moduleKey: string;
    bookOsis: string;
    chapter: number;
    onVerseHighlight?: (verseNumber: number | null) => void;
    onChapterEnd?: () => void;
}

type PlaybackSpeed = 0.5 | 0.75 | 1 | 1.25 | 1.5 | 2;
type SleepTimer = null | 15 | 30 | 60 | 'chapter';

const SPEEDS: PlaybackSpeed[] = [0.5, 0.75, 1, 1.25, 1.5, 2];

const SLEEP_OPTIONS: { label: string; value: SleepTimer }[] = [
    { label: 'Off', value: null },
    { label: '15 min', value: 15 },
    { label: '30 min', value: 30 },
    { label: '1 hour', value: 60 },
    { label: 'End of chapter', value: 'chapter' },
];

// ── Component ───────────────────────────────────────────────────

export default function AudioPlayer({ moduleKey, bookOsis, chapter, onVerseHighlight, onChapterEnd }: AudioPlayerProps) {
    const [audio, setAudio] = useState<AudioData | null>(null);
    const [loading, setLoading] = useState(false);
    const [available, setAvailable] = useState<boolean | null>(null);
    const [playing, setPlaying] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);
    const [speed, setSpeed] = useState<PlaybackSpeed>(1);
    const [showSpeedMenu, setShowSpeedMenu] = useState(false);
    const [showSleepMenu, setShowSleepMenu] = useState(false);
    const [sleepTimer, setSleepTimer] = useState<SleepTimer>(null);
    const [sleepTimeLeft, setSleepTimeLeft] = useState<number | null>(null);
    const [expanded, setExpanded] = useState(false);
    const [volume, setVolume] = useState(1);

    const audioRef = useRef<HTMLAudioElement | null>(null);
    const sleepIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const timeUpdateRef = useRef<number>(0);

    // Check availability on chapter change
    useEffect(() => {
        setAudio(null);
        setAvailable(null);
        setPlaying(false);
        setCurrentTime(0);
        setDuration(0);

        if (audioRef.current) {
            audioRef.current.pause();
            audioRef.current.src = '';
            audioRef.current = null;
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta?.getAttribute('content') ?? '';

        fetch(`/audio/check/${moduleKey}/${bookOsis}/${chapter}`, {
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => setAvailable(data.available))
            .catch(() => setAvailable(false));
    }, [moduleKey, bookOsis, chapter]);

    // Load audio data
    const loadAudio = useCallback(() => {
        if (loading || audio) return;
        setLoading(true);

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta?.getAttribute('content') ?? '';

        fetch(`/audio/${moduleKey}/${bookOsis}/${chapter}`, {
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => {
                if (data.data) {
                    setAudio(data.data);
                    setExpanded(true);
                }
            })
            .catch(() => setAvailable(false))
            .finally(() => setLoading(false));
    }, [moduleKey, bookOsis, chapter, loading, audio]);

    // Initialize HTML Audio when audio data loads
    useEffect(() => {
        if (!audio) return;

        const el = new Audio(audio.stream_url);
        el.preload = 'metadata';
        el.playbackRate = speed;
        el.volume = volume;

        el.addEventListener('loadedmetadata', () => {
            setDuration(el.duration);
        });

        el.addEventListener('timeupdate', () => {
            setCurrentTime(el.currentTime);
            timeUpdateRef.current = el.currentTime;

            // Verse-level sync
            if (audio.verse_timings && onVerseHighlight) {
                const current = el.currentTime;
                const activeVerse = audio.verse_timings.find(
                    vt => current >= vt.start && current < vt.end
                );
                onVerseHighlight(activeVerse?.verse ?? null);
            }
        });

        el.addEventListener('ended', () => {
            setPlaying(false);
            onVerseHighlight?.(null);
            if (sleepTimer !== 'chapter') {
                onChapterEnd?.();
            }
        });

        el.addEventListener('play', () => setPlaying(true));
        el.addEventListener('pause', () => setPlaying(false));

        audioRef.current = el;

        // Auto-play
        el.play().catch(() => {});

        return () => {
            el.pause();
            el.src = '';
            audioRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [audio]);

    // Sync playback speed
    useEffect(() => {
        if (audioRef.current) audioRef.current.playbackRate = speed;
    }, [speed]);

    // Sync volume
    useEffect(() => {
        if (audioRef.current) audioRef.current.volume = volume;
    }, [volume]);

    // Sleep timer countdown
    useEffect(() => {
        if (sleepIntervalRef.current) {
            clearInterval(sleepIntervalRef.current);
            sleepIntervalRef.current = null;
        }

        if (sleepTimer === null || sleepTimer === 'chapter') {
            setSleepTimeLeft(null);
            return;
        }

        setSleepTimeLeft(sleepTimer * 60);
        sleepIntervalRef.current = setInterval(() => {
            setSleepTimeLeft(prev => {
                if (prev === null || prev <= 1) {
                    audioRef.current?.pause();
                    setSleepTimer(null);
                    return null;
                }
                return prev - 1;
            });
        }, 1000);

        return () => {
            if (sleepIntervalRef.current) clearInterval(sleepIntervalRef.current);
        };
    }, [sleepTimer]);

    // Play/Pause toggle
    const togglePlayback = useCallback(() => {
        if (!audioRef.current) {
            loadAudio();
            return;
        }
        if (playing) {
            audioRef.current.pause();
        } else {
            audioRef.current.play().catch(() => {});
        }
    }, [playing, loadAudio]);

    // Seek
    const seek = useCallback((time: number) => {
        if (!audioRef.current) return;
        audioRef.current.currentTime = Math.max(0, Math.min(time, duration));
    }, [duration]);

    // Skip forward/back
    const skipForward = useCallback(() => seek(currentTime + 30), [seek, currentTime]);
    const skipBack = useCallback(() => seek(currentTime - 30), [seek, currentTime]);

    // Format time
    const formatTime = (seconds: number): string => {
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    };

    // Don't render if no audio available
    if (available === false || available === null) {
        if (available === null) return null;
        return null;
    }

    // Collapsed state — just the play button in toolbar
    if (!expanded && !audio) {
        return (
            <button
                onClick={() => { setExpanded(true); loadAudio(); }}
                disabled={loading}
                className="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                title="Play audio"
            >
                {loading ? (
                    <LoadingSpinner />
                ) : (
                    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" />
                    </svg>
                )}
                <span className="hidden sm:inline">Audio</span>
            </button>
        );
    }

    return (
        <div className="fixed bottom-0 left-0 right-0 z-40 sm:bottom-0 mb-16 sm:mb-0">
            <div className="bg-white/95 dark:bg-gray-900/95 backdrop-blur-lg border-t border-gray-200 dark:border-gray-700 shadow-lg">
                <div className="mx-auto max-w-5xl px-4">
                    {/* Progress bar (clickable) */}
                    <div
                        className="relative h-1 bg-gray-200 dark:bg-gray-700 cursor-pointer group -mx-4 px-4"
                        onClick={(e) => {
                            if (!duration) return;
                            const rect = e.currentTarget.getBoundingClientRect();
                            const pct = (e.clientX - rect.left) / rect.width;
                            seek(pct * duration);
                        }}
                    >
                        <div
                            className="absolute inset-y-0 left-0 bg-indigo-600 dark:bg-indigo-400 transition-[width] duration-100"
                            style={{ width: duration ? `${(currentTime / duration) * 100}%` : '0%' }}
                        />
                        <div className="absolute inset-y-0 left-0 right-0 group-hover:bg-gray-300/30 dark:group-hover:bg-gray-600/30 transition-colors" />
                    </div>

                    {/* Controls */}
                    <div className="flex items-center gap-3 py-2">
                        {/* Chapter info */}
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-medium text-gray-900 dark:text-white truncate">
                                {bookOsis} {chapter}
                            </p>
                            {audio?.narrator && (
                                <p className="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                    {audio.narrator}
                                </p>
                            )}
                        </div>

                        {/* Time display */}
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 tabular-nums hidden sm:block">
                            {formatTime(currentTime)}
                        </span>

                        {/* Skip back 30s */}
                        <button
                            onClick={skipBack}
                            className="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                            title="Back 30s"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                            </svg>
                        </button>

                        {/* Play/Pause */}
                        <button
                            onClick={togglePlayback}
                            disabled={loading}
                            className="p-2 rounded-full bg-indigo-600 text-white hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            title={playing ? 'Pause' : 'Play'}
                        >
                            {loading ? (
                                <LoadingSpinner />
                            ) : playing ? (
                                <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                                </svg>
                            ) : (
                                <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            )}
                        </button>

                        {/* Skip forward 30s */}
                        <button
                            onClick={skipForward}
                            className="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                            title="Forward 30s"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                            </svg>
                        </button>

                        {/* Time remaining */}
                        <span className="text-[10px] text-gray-500 dark:text-gray-400 tabular-nums hidden sm:block">
                            {duration ? `-${formatTime(duration - currentTime)}` : '--:--'}
                        </span>

                        {/* Speed control */}
                        <div className="relative hidden sm:block">
                            <button
                                onClick={() => { setShowSpeedMenu(!showSpeedMenu); setShowSleepMenu(false); }}
                                className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
                                    speed !== 1
                                        ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20'
                                        : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                                }`}
                                title="Playback speed"
                            >
                                {speed}x
                            </button>
                            {showSpeedMenu && (
                                <div className="absolute bottom-full right-0 mb-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 min-w-[80px]">
                                    {SPEEDS.map(s => (
                                        <button
                                            key={s}
                                            onClick={() => { setSpeed(s); setShowSpeedMenu(false); }}
                                            className={`w-full px-3 py-1.5 text-sm text-left transition-colors ${
                                                s === speed
                                                    ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20'
                                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                            }`}
                                        >
                                            {s}x
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Sleep timer */}
                        <div className="relative hidden sm:block">
                            <button
                                onClick={() => { setShowSleepMenu(!showSleepMenu); setShowSpeedMenu(false); }}
                                className={`p-1.5 rounded transition-colors ${
                                    sleepTimer !== null
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                                }`}
                                title={sleepTimeLeft ? `Sleep in ${formatTime(sleepTimeLeft)}` : 'Sleep timer'}
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                                </svg>
                            </button>
                            {showSleepMenu && (
                                <div className="absolute bottom-full right-0 mb-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 min-w-[140px]">
                                    {SLEEP_OPTIONS.map(opt => (
                                        <button
                                            key={String(opt.value)}
                                            onClick={() => { setSleepTimer(opt.value); setShowSleepMenu(false); }}
                                            className={`w-full px-3 py-1.5 text-sm text-left transition-colors ${
                                                sleepTimer === opt.value
                                                    ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20'
                                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                            }`}
                                        >
                                            {opt.label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Volume (desktop) */}
                        <div className="hidden md:flex items-center gap-1">
                            <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                            </svg>
                            <input
                                type="range"
                                min="0"
                                max="1"
                                step="0.05"
                                value={volume}
                                onChange={(e) => setVolume(parseFloat(e.target.value))}
                                className="w-20 h-1 accent-indigo-600"
                            />
                        </div>

                        {/* Close */}
                        <button
                            onClick={() => {
                                audioRef.current?.pause();
                                if (audioRef.current) {
                                    audioRef.current.src = '';
                                    audioRef.current = null;
                                }
                                setAudio(null);
                                setExpanded(false);
                                setPlaying(false);
                                onVerseHighlight?.(null);
                            }}
                            className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            title="Close player"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ── Loading Spinner ─────────────────────────────────────────────

function LoadingSpinner() {
    return (
        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}
