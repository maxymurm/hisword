import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { useSync, SyncStatus } from '@/hooks/useSync';
import { isPushSupported, isSubscribed, subscribeToPush, unsubscribeFromPush } from '@/services/pushNotifications';

interface Props {
    user: { name: string; email: string } | null;
    appVersion: string;
    phpVersion: string;
    laravelVersion: string;
}

export default function Settings({ user, appVersion, phpVersion, laravelVersion }: Props) {
    const { status: syncStatus, pendingCount, lastSync, syncNow } = useSync();
    const [fontSize, setFontSize] = useState(() => parseInt(localStorage.getItem('ps-font-size') || '18', 10));
    const [viewMode, setViewMode] = useState<'verse' | 'paragraph'>(() =>
        (localStorage.getItem('ps-view-mode') as 'verse' | 'paragraph') || 'verse'
    );
    const [showStrongs, setShowStrongs] = useState(() => localStorage.getItem('ps-show-strongs') === 'true');
    const [showRedLetters, setShowRedLetters] = useState(() => localStorage.getItem('ps-red-letters') !== 'false');
    const [theme, setTheme] = useState<'system' | 'light' | 'dark'>(() =>
        (localStorage.getItem('ps-theme') as 'system' | 'light' | 'dark') || 'system'
    );
    const [language, setLanguage] = useState(() => localStorage.getItem('ps-language') || 'en');
    const [cacheSize, setCacheSize] = useState<string>('Calculating...');
    const [pushEnabled, setPushEnabled] = useState(false);
    const pushSupported = isPushSupported();

    useEffect(() => {
        // Calculate cache size
        if ('caches' in window) {
            caches.keys().then(async (names) => {
                let total = 0;
                for (const name of names) {
                    const cache = await caches.open(name);
                    const keys = await cache.keys();
                    total += keys.length;
                }
                setCacheSize(`${total} cached items`);
            }).catch(() => setCacheSize('Unknown'));
        } else {
            setCacheSize('Not available');
        }

        // Check push subscription status
        if (pushSupported) {
            isSubscribed().then(setPushEnabled);
        }
    }, [pushSupported]);

    const saveSetting = (key: string, value: string) => {
        localStorage.setItem(key, value);
    };

    const clearCache = async () => {
        if ('caches' in window) {
            const names = await caches.keys();
            await Promise.all(names.map(name => caches.delete(name)));
            setCacheSize('0 cached items');
        }
    };

    return (
        <AppLayout title="Settings">
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-8">Settings</h1>

                {/* Reader Settings */}
                <SettingsSection title="Reader" icon="📖">
                    <SettingsRow label="Font Size" description={`${fontSize}px`}>
                        <input
                            type="range"
                            min={14}
                            max={28}
                            value={fontSize}
                            onChange={e => {
                                const v = parseInt(e.target.value, 10);
                                setFontSize(v);
                                saveSetting('ps-font-size', String(v));
                            }}
                            className="w-32 accent-indigo-600"
                        />
                    </SettingsRow>

                    <SettingsRow label="View Mode">
                        <select
                            value={viewMode}
                            onChange={e => {
                                const v = e.target.value as 'verse' | 'paragraph';
                                setViewMode(v);
                                saveSetting('ps-view-mode', v);
                            }}
                            className="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm"
                        >
                            <option value="verse">Verse-by-verse</option>
                            <option value="paragraph">Paragraph</option>
                        </select>
                    </SettingsRow>

                    <SettingsRow label="Strong's Numbers">
                        <Toggle checked={showStrongs} onChange={v => { setShowStrongs(v); saveSetting('ps-show-strongs', String(v)); }} />
                    </SettingsRow>

                    <SettingsRow label="Red Letters (Words of Christ)">
                        <Toggle checked={showRedLetters} onChange={v => { setShowRedLetters(v); saveSetting('ps-red-letters', String(v)); }} />
                    </SettingsRow>
                </SettingsSection>

                {/* Appearance */}
                <SettingsSection title="Appearance" icon="🎨">
                    <SettingsRow label="Theme">
                        <select
                            value={theme}
                            onChange={e => {
                                const v = e.target.value as 'system' | 'light' | 'dark';
                                setTheme(v);
                                saveSetting('ps-theme', v);
                            }}
                            className="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm"
                        >
                            <option value="system">System</option>
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </SettingsRow>

                    <SettingsRow label="Language">
                        <select
                            value={language}
                            onChange={e => {
                                setLanguage(e.target.value);
                                saveSetting('ps-language', e.target.value);
                            }}
                            className="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm"
                        >
                            <option value="en">English</option>
                            <option value="es">Español</option>
                            <option value="fr">Français</option>
                            <option value="de">Deutsch</option>
                            <option value="pt">Português</option>
                            <option value="ar">العربية</option>
                            <option value="zh">中文</option>
                            <option value="ko">한국어</option>
                            <option value="ja">日本語</option>
                            <option value="ru">Русский</option>
                        </select>
                    </SettingsRow>
                </SettingsSection>

                {/* Storage & Sync */}
                <SettingsSection title="Storage & Sync" icon="☁️">
                    <SettingsRow label="Cache Status" description={cacheSize}>
                        <button
                            onClick={clearCache}
                            className="rounded-lg bg-red-50 dark:bg-red-950/30 px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                        >
                            Clear Cache
                        </button>
                    </SettingsRow>

                    <SettingsRow label="Sync Status" description={pendingCount > 0 ? `${pendingCount} pending` : lastSync ? `Last: ${new Date(lastSync).toLocaleString()}` : undefined}>
                        <div className="flex items-center gap-2">
                            <SyncIndicator status={syncStatus} />
                            {syncStatus === 'pending' && (
                                <button onClick={syncNow} className="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Sync Now
                                </button>
                            )}
                        </div>
                    </SettingsRow>

                    {pushSupported && (
                        <SettingsRow label="Push Notifications" description={Notification.permission === 'denied' ? 'Blocked by browser' : undefined}>
                            <Toggle
                                checked={pushEnabled}
                                onChange={async (v) => {
                                    if (v) {
                                        const ok = await subscribeToPush();
                                        setPushEnabled(ok);
                                    } else {
                                        await unsubscribeFromPush();
                                        setPushEnabled(false);
                                    }
                                }}
                            />
                        </SettingsRow>
                    )}
                </SettingsSection>

                {/* Account */}
                {user && (
                    <SettingsSection title="Account" icon="👤">
                        <SettingsRow label="Name">
                            <span className="text-sm text-gray-700 dark:text-gray-300">{user.name}</span>
                        </SettingsRow>
                        <SettingsRow label="Email">
                            <span className="text-sm text-gray-700 dark:text-gray-300">{user.email}</span>
                        </SettingsRow>
                        <SettingsRow label="Profile">
                            <a href="/profile" className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                Edit Profile →
                            </a>
                        </SettingsRow>
                    </SettingsSection>
                )}

                {/* About */}
                <SettingsSection title="About" icon="ℹ️">
                    <SettingsRow label="App Version">
                        <span className="text-sm text-gray-500 dark:text-gray-400">{appVersion}</span>
                    </SettingsRow>
                    <SettingsRow label="PHP">
                        <span className="text-sm text-gray-500 dark:text-gray-400">{phpVersion}</span>
                    </SettingsRow>
                    <SettingsRow label="Laravel">
                        <span className="text-sm text-gray-500 dark:text-gray-400">{laravelVersion}</span>
                    </SettingsRow>
                </SettingsSection>
            </div>
        </AppLayout>
    );
}

function SettingsSection({ title, icon, children }: { title: string; icon: string; children: React.ReactNode }) {
    return (
        <div className="mb-8">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <span>{icon}</span> {title}
            </h2>
            <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                {children}
            </div>
        </div>
    );
}

function SettingsRow({ label, description, children }: { label: string; description?: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between px-4 py-3.5">
            <div>
                <div className="text-sm font-medium text-gray-900 dark:text-white">{label}</div>
                {description && <div className="text-xs text-gray-500 dark:text-gray-400">{description}</div>}
            </div>
            <div>{children}</div>
        </div>
    );
}

function Toggle({ checked, onChange }: { checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <button
            role="switch"
            aria-checked={checked}
            onClick={() => onChange(!checked)}
            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                checked ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'
            }`}
        >
            <span className={`inline-block h-4 w-4 rounded-full bg-white transition-transform ${
                checked ? 'translate-x-6' : 'translate-x-1'
            }`} />
        </button>
    );
}

const SYNC_LABELS: Record<SyncStatus, { label: string; color: string; dot: string }> = {
    synced:  { label: 'Synced',         color: 'text-green-600 dark:text-green-400',   dot: 'bg-green-500' },
    pending: { label: 'Pending',        color: 'text-amber-600 dark:text-amber-400',   dot: 'bg-amber-500' },
    syncing: { label: 'Syncing…',       color: 'text-blue-600 dark:text-blue-400',     dot: 'bg-blue-500 animate-pulse' },
    offline: { label: 'Offline',        color: 'text-gray-500 dark:text-gray-400',     dot: 'bg-gray-400' },
    error:   { label: 'Sync Error',     color: 'text-red-600 dark:text-red-400',       dot: 'bg-red-500' },
};

function SyncIndicator({ status }: { status: SyncStatus }) {
    const { label, color, dot } = SYNC_LABELS[status];
    return (
        <span className={`flex items-center gap-1.5 text-sm ${color}`}>
            <span className={`h-2 w-2 rounded-full ${dot}`} />
            {label}
        </span>
    );
}
