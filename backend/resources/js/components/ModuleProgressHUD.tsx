import { useEffect, useRef, useState } from 'react';

export interface DownloadProgress {
    module: string;
    message: string;
    percent: number;
    status: 'queued' | 'in_progress' | 'completed' | 'failed' | 'waiting' | 'idle';
    timestamp?: string;
}

interface Props {
    moduleKey: string;
    moduleName: string;
    onComplete?: (moduleKey: string) => void;
    onFailed?: (moduleKey: string, message: string) => void;
    initialProgress?: DownloadProgress;
}

/**
 * Module Download Progress HUD
 *
 * Mirrors HisWord's MBProgressHUD (MBProgressHUDModeDeterminate):
 * - Circular/linear progress bar during download
 * - Tick icon on success
 * - Cross icon on failure
 * - Auto-dismiss after completion
 */
export default function ModuleProgressHUD({
    moduleKey,
    moduleName,
    onComplete,
    onFailed,
    initialProgress,
}: Props) {
    const [progress, setProgress] = useState<DownloadProgress>(
        initialProgress ?? {
            module: moduleKey,
            message: 'Queued for installation...',
            percent: 0,
            status: 'queued',
        }
    );
    const [visible, setVisible] = useState(true);
    const eventSourceRef = useRef<EventSource | null>(null);
    const pollIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        // Try SSE first, fall back to polling
        let usePolling = false;

        try {
            const es = new EventSource(`/modules/${moduleKey}/progress`);
            eventSourceRef.current = es;

            es.onmessage = (event) => {
                try {
                    const data: DownloadProgress = JSON.parse(event.data);
                    setProgress(data);

                    if (data.status === 'completed') {
                        onComplete?.(moduleKey);
                        es.close();
                        // Auto-hide after 2s (like HisWord's 1s delay)
                        setTimeout(() => setVisible(false), 2000);
                    } else if (data.status === 'failed') {
                        onFailed?.(moduleKey, data.message);
                        es.close();
                        setTimeout(() => setVisible(false), 4000);
                    }
                } catch { /* ignore parse errors */ }
            };

            es.addEventListener('done', (event) => {
                es.close();
            });

            es.onerror = () => {
                es.close();
                // Fall back to polling
                if (!usePolling) {
                    usePolling = true;
                    startPolling();
                }
            };
        } catch {
            usePolling = true;
            startPolling();
        }

        function startPolling() {
            pollIntervalRef.current = setInterval(async () => {
                try {
                    const res = await fetch(`/modules/${moduleKey}/progress-poll`);
                    const data: DownloadProgress = await res.json();
                    setProgress(data);

                    if (data.status === 'completed') {
                        onComplete?.(moduleKey);
                        clearInterval(pollIntervalRef.current!);
                        setTimeout(() => setVisible(false), 2000);
                    } else if (data.status === 'failed') {
                        onFailed?.(moduleKey, data.message);
                        clearInterval(pollIntervalRef.current!);
                        setTimeout(() => setVisible(false), 4000);
                    }
                } catch { /* ignore */ }
            }, 800);
        }

        return () => {
            eventSourceRef.current?.close();
            if (pollIntervalRef.current) clearInterval(pollIntervalRef.current);
        };
    }, [moduleKey]);

    if (!visible) return null;

    const isTerminal = progress.status === 'completed' || progress.status === 'failed';
    const pct = Math.max(0, Math.min(100, progress.percent));

    return (
        <div className="relative overflow-hidden rounded-xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-lg transition-all duration-300">
            {/* Progress bar (absolute behind content) */}
            <div
                className={`absolute inset-0 transition-all duration-500 ease-out ${
                    progress.status === 'completed'
                        ? 'bg-green-50 dark:bg-green-950/30'
                        : progress.status === 'failed'
                        ? 'bg-red-50 dark:bg-red-950/30'
                        : 'bg-transparent'
                }`}
            />

            {/* Linear progress bar */}
            <div className="absolute bottom-0 left-0 right-0 h-1 bg-gray-100 dark:bg-gray-800">
                <div
                    className={`h-full transition-all duration-500 ease-out ${
                        progress.status === 'completed'
                            ? 'bg-green-500'
                            : progress.status === 'failed'
                            ? 'bg-red-500'
                            : 'bg-indigo-500'
                    }`}
                    style={{ width: `${pct}%` }}
                />
            </div>

            <div className="relative flex items-center gap-4 p-4">
                {/* Circular progress indicator */}
                <div className="flex-shrink-0">
                    {progress.status === 'completed' ? (
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50 text-green-600 dark:text-green-400 animate-scale-in">
                            <CheckIcon />
                        </div>
                    ) : progress.status === 'failed' ? (
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 animate-scale-in">
                            <CrossIcon />
                        </div>
                    ) : (
                        <CircularProgress percent={pct} />
                    )}
                </div>

                {/* Module info */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                        <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {progress.status === 'completed' ? 'Installed' : progress.status === 'failed' ? 'Error' : 'Installing'}
                        </h4>
                        {!isTerminal && (
                            <span className="text-xs font-mono text-gray-500 dark:text-gray-400 ml-2">
                                {pct}%
                            </span>
                        )}
                    </div>
                    <p className="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                        {progress.message || moduleName}
                    </p>
                </div>
            </div>
        </div>
    );
}

/**
 * Circular progress indicator (like MBProgressHUDModeDeterminate).
 */
function CircularProgress({ percent }: { percent: number }) {
    const radius = 16;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (percent / 100) * circumference;

    return (
        <div className="relative h-10 w-10">
            <svg className="h-10 w-10 -rotate-90" viewBox="0 0 40 40">
                {/* Background circle */}
                <circle
                    cx="20"
                    cy="20"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                    className="text-gray-200 dark:text-gray-700"
                />
                {/* Progress arc */}
                <circle
                    cx="20"
                    cy="20"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className="text-indigo-500 transition-all duration-500 ease-out"
                />
            </svg>
            {/* Spinner overlay when queued/waiting */}
            {percent === 0 && (
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-500" />
                </div>
            )}
        </div>
    );
}

/** HisWord-style tick icon (37x-Tick.png equivalent) */
function CheckIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
    );
}

/** HisWord-style cross icon (37x-Cross.png equivalent) */
function CrossIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

/**
 * Floating overlay HUD — shows on top of page content (like MBProgressHUD on a view).
 */
export function ModuleProgressOverlay({
    moduleKey,
    moduleName,
    onComplete,
    onFailed,
    onDismiss,
}: Props & { onDismiss?: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center pointer-events-none">
            <div className="pointer-events-auto w-72">
                <ModuleProgressHUD
                    moduleKey={moduleKey}
                    moduleName={moduleName}
                    onComplete={(key) => {
                        onComplete?.(key);
                        setTimeout(() => onDismiss?.(), 2500);
                    }}
                    onFailed={(key, msg) => {
                        onFailed?.(key, msg);
                        setTimeout(() => onDismiss?.(), 4000);
                    }}
                />
            </div>
        </div>
    );
}
