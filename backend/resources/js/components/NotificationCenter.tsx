import { useCallback, useEffect, useState } from 'react';

interface Notification {
    id: string;
    type: string;
    title: string;
    body: string;
    data: Record<string, string> | null;
    status: string;
    read_at: string | null;
    created_at: string;
}

interface NotificationCenterProps {
    isOpen: boolean;
    onClose: () => void;
}

const TYPE_ICONS: Record<string, string> = {
    verse_of_day: '📖',
    reading_plan: '📅',
    new_module: '📥',
    sync: '🔄',
};

export default function NotificationCenter({ isOpen, onClose }: NotificationCenterProps) {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(true);

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    const fetchNotifications = useCallback(async () => {
        try {
            const res = await fetch('/notifications', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setNotifications(data.data || []);
                setUnreadCount(data.unread_count || 0);
            }
        } catch {
            // Silently fail
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (isOpen) {
            fetchNotifications();
        }
    }, [isOpen, fetchNotifications]);

    const markRead = useCallback(async (id: string) => {
        try {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
            setNotifications(prev =>
                prev.map(n => n.id === id ? { ...n, status: 'read', read_at: new Date().toISOString() } : n)
            );
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch {
            // Silently fail
        }
    }, [csrfToken]);

    const markAllRead = useCallback(async () => {
        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
            setNotifications(prev =>
                prev.map(n => ({ ...n, status: 'read', read_at: n.read_at || new Date().toISOString() }))
            );
            setUnreadCount(0);
        } catch {
            // Silently fail
        }
    }, [csrfToken]);

    const handleNotificationClick = useCallback((notification: Notification) => {
        if (!notification.read_at) {
            markRead(notification.id);
        }
        // Navigate to deep link if available
        if (notification.data?.deep_link) {
            window.location.href = notification.data.deep_link;
        }
    }, [markRead]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50" onClick={onClose}>
            <div className="fixed inset-0 bg-black/20" />
            <div
                className="absolute right-4 top-14 w-96 max-h-[70vh] bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col animate-in slide-in-from-top-2"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <div className="flex items-center gap-2">
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Notifications
                        </h3>
                        {unreadCount > 0 && (
                            <span className="inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full bg-indigo-600 text-white text-xs font-medium">
                                {unreadCount}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllRead}
                                className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                Mark all read
                            </button>
                        )}
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Notification List */}
                <div className="overflow-y-auto flex-1">
                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <div className="h-6 w-6 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin" />
                        </div>
                    ) : notifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                            <span className="text-3xl mb-2">🔔</span>
                            <p className="text-sm">No notifications yet</p>
                        </div>
                    ) : (
                        notifications.map(notification => (
                            <button
                                key={notification.id}
                                onClick={() => handleNotificationClick(notification)}
                                className={`w-full text-left px-4 py-3 border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors ${
                                    !notification.read_at ? 'bg-indigo-50/50 dark:bg-indigo-950/30' : ''
                                }`}
                            >
                                <div className="flex gap-3">
                                    <span className="text-lg mt-0.5">
                                        {TYPE_ICONS[notification.type] || '🔔'}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className={`text-sm ${
                                                !notification.read_at
                                                    ? 'font-semibold text-gray-900 dark:text-gray-100'
                                                    : 'text-gray-700 dark:text-gray-300'
                                            }`}>
                                                {notification.title}
                                            </p>
                                            {!notification.read_at && (
                                                <span className="h-2 w-2 rounded-full bg-indigo-600 flex-shrink-0" />
                                            )}
                                        </div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">
                                            {notification.body}
                                        </p>
                                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                            {formatTimeAgo(notification.created_at)}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

function formatTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
}
