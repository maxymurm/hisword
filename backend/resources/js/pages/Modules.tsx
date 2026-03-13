import { useState, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import type { PageProps, PaginatedResponse } from '@/types';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import ModuleProgressHUD, { type DownloadProgress } from '@/components/ModuleProgressHUD';

interface Module {
    id: string;
    key: string;
    name: string;
    description: string | null;
    type: string;
    language: string;
    version: string | null;
    is_installed: boolean;
    is_bundled: boolean;
    mod_drv: string | null;
    encoding: string | null;
    direction: string | null;
    category: string | null;
    about: string | null;
    file_size: number | null;
    install_size: number | null;
}

interface ModuleSource {
    id: string;
    caption: string;
    type: string;
    server: string;
    directory: string;
    is_active: boolean;
    last_refreshed: string | null;
}

interface Props extends PageProps {
    modules: PaginatedResponse<Module>;
    counts: { total: number; installed: number; available: number };
    types: string[];
    languages: string[];
    filters: {
        type: string | null;
        language: string | null;
        search: string | null;
        filter: string;
    };
    activeDownloads: Record<string, DownloadProgress>;
    sources: ModuleSource[];
}

const TYPE_ICONS: Record<string, string> = {
    bible: '📖',
    commentary: '📝',
    dictionary: '📚',
    lexicon: '🔤',
    genbook: '📕',
    devotional: '🙏',
    'daily devotional': '📅',
};

const TYPE_COLORS: Record<string, string> = {
    bible: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    commentary: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    dictionary: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    lexicon: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300',
    genbook: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    devotional: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300',
    'daily devotional': 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
};

export default function Modules({ modules, counts, types, languages, filters, activeDownloads, sources }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [installing, setInstalling] = useState<Record<string, boolean>>(
        Object.fromEntries(Object.keys(activeDownloads).map(k => [k, true]))
    );
    const [detailModule, setDetailModule] = useState<Module | null>(null);
    const [refreshing, setRefreshing] = useState(false);
    const [confirmUninstall, setConfirmUninstall] = useState<Module | null>(null);

    const handleSearch = useCallback((e: React.FormEvent) => {
        e.preventDefault();
        router.get('/modules', { ...filters, search: search || undefined }, { preserveState: true });
    }, [search, filters]);

    const handleFilter = useCallback((key: string, value: string | undefined) => {
        router.get('/modules', { ...filters, [key]: value }, { preserveState: true });
    }, [filters]);

    const handleInstall = useCallback(async (moduleKey: string) => {
        setInstalling(prev => ({ ...prev, [moduleKey]: true }));

        try {
            const res = await fetch(`/modules/${moduleKey}/install`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            if (!res.ok) {
                const data = await res.json();
                alert(data.error || 'Installation failed');
                setInstalling(prev => ({ ...prev, [moduleKey]: false }));
            }
            // Progress tracking is handled by ModuleProgressHUD
        } catch (err) {
            alert('Network error. Please try again.');
            setInstalling(prev => ({ ...prev, [moduleKey]: false }));
        }
    }, []);

    const handleUninstall = useCallback(async (module: Module) => {
        try {
            const res = await fetch(`/modules/${module.key}/uninstall`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            if (res.ok) {
                setConfirmUninstall(null);
                router.reload({ only: ['modules', 'counts'] });
            } else {
                const data = await res.json();
                alert(data.error || 'Uninstall failed');
            }
        } catch {
            alert('Network error.');
        }
    }, []);

    const handleRefreshSources = useCallback(async () => {
        setRefreshing(true);
        try {
            const res = await fetch('/modules/refresh-sources', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            if (res.ok) {
                router.reload();
            } else {
                const data = await res.json();
                alert(data.error || 'Refresh failed');
            }
        } catch {
            alert('Network error.');
        } finally {
            setRefreshing(false);
        }
    }, []);

    const handleDownloadComplete = useCallback((moduleKey: string) => {
        // Refresh the page data after a short delay
        setTimeout(() => {
            setInstalling(prev => {
                const next = { ...prev };
                delete next[moduleKey];
                return next;
            });
            router.reload({ only: ['modules', 'counts'] });
        }, 2500);
    }, []);

    const handleDownloadFailed = useCallback((moduleKey: string) => {
        setTimeout(() => {
            setInstalling(prev => {
                const next = { ...prev };
                delete next[moduleKey];
                return next;
            });
        }, 4500);
    }, []);

    const formatSize = (bytes: number | null) => {
        if (!bytes) return null;
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    return (
        <AppLayout title="Module Library">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Module Library
                        </h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Browse, install, and manage Bible modules, commentaries, dictionaries, and more.
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        size="sm"
                        loading={refreshing}
                        onClick={handleRefreshSources}
                    >
                        <RefreshIcon />
                        Refresh Sources
                    </Button>
                </div>

                {/* Stats bar */}
                <div className="grid grid-cols-3 gap-4">
                    <StatCard
                        label="Total"
                        value={counts.total}
                        active={filters.filter === 'all'}
                        onClick={() => handleFilter('filter', 'all')}
                    />
                    <StatCard
                        label="Installed"
                        value={counts.installed}
                        active={filters.filter === 'installed'}
                        onClick={() => handleFilter('filter', 'installed')}
                        color="green"
                    />
                    <StatCard
                        label="Available"
                        value={counts.available}
                        active={filters.filter === 'available'}
                        onClick={() => handleFilter('filter', 'available')}
                        color="blue"
                    />
                </div>

                {/* Active downloads */}
                {Object.keys(installing).length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Active Downloads
                        </h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {Object.keys(installing).map(key => (
                                <ModuleProgressHUD
                                    key={key}
                                    moduleKey={key}
                                    moduleName={modules.data.find(m => m.key === key)?.name ?? key}
                                    onComplete={handleDownloadComplete}
                                    onFailed={handleDownloadFailed}
                                    initialProgress={activeDownloads[key]}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* Filters */}
                <Card padding={false}>
                    <div className="p-4 flex flex-col sm:flex-row gap-3">
                        {/* Search */}
                        <form onSubmit={handleSearch} className="flex-1">
                            <div className="relative">
                                <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search modules..."
                                    className="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                            </div>
                        </form>

                        {/* Type filter */}
                        <select
                            value={filters.type ?? ''}
                            onChange={(e) => handleFilter('type', e.target.value || undefined)}
                            className="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">All Types</option>
                            {types.map(t => (
                                <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                            ))}
                        </select>

                        {/* Language filter */}
                        <select
                            value={filters.language ?? ''}
                            onChange={(e) => handleFilter('language', e.target.value || undefined)}
                            className="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">All Languages</option>
                            {languages.map(l => (
                                <option key={l} value={l}>{l}</option>
                            ))}
                        </select>
                    </div>
                </Card>

                {/* Module grid */}
                {modules.data.length === 0 ? (
                    <Card>
                        <div className="text-center py-12">
                            <p className="text-gray-500 dark:text-gray-400">
                                {counts.total === 0
                                    ? 'No modules found. Click "Refresh Sources" to fetch the module catalog.'
                                    : 'No modules match your filters.'}
                            </p>
                        </div>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {modules.data.map((mod) => (
                            <ModuleCard
                                key={mod.id}
                                module={mod}
                                isInstalling={!!installing[mod.key]}
                                onInstall={() => handleInstall(mod.key)}
                                onUninstall={() => setConfirmUninstall(mod)}
                                onDetails={() => setDetailModule(mod)}
                            />
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {modules.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {modules.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : link.url
                                        ? 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                                        : 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Module details modal */}
            <Modal open={!!detailModule} onClose={() => setDetailModule(null)} title={detailModule?.name ?? ''} maxWidth="lg">
                {detailModule && <ModuleDetails module={detailModule} />}
            </Modal>

            {/* Confirm uninstall modal */}
            <Modal open={!!confirmUninstall} onClose={() => setConfirmUninstall(null)} title="Remove Module">
                {confirmUninstall && (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Are you sure you want to remove <strong>{confirmUninstall.name}</strong> ({confirmUninstall.key})?
                            This will delete all indexed data for this module.
                        </p>
                        <div className="flex justify-end gap-3">
                            <Button variant="ghost" size="sm" onClick={() => setConfirmUninstall(null)}>
                                Cancel
                            </Button>
                            <Button variant="danger" size="sm" onClick={() => handleUninstall(confirmUninstall)}>
                                Remove Module
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

/* ─── Sub-components ──────────────────────────────────────────────── */

function ModuleCard({
    module,
    isInstalling,
    onInstall,
    onUninstall,
    onDetails,
}: {
    module: Module;
    isInstalling: boolean;
    onInstall: () => void;
    onUninstall: () => void;
    onDetails: () => void;
}) {
    const typeColor = TYPE_COLORS[module.type] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    const typeIcon = TYPE_ICONS[module.type] ?? '📄';

    return (
        <Card padding={false} className="group flex flex-col">
            <div className="p-4 flex-1">
                <div className="flex items-start justify-between mb-2">
                    <div className="flex items-center gap-2">
                        <span className="text-lg">{typeIcon}</span>
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                                {module.name || module.key}
                            </h3>
                            <span className="text-xs text-gray-400 font-mono">{module.key}</span>
                        </div>
                    </div>
                    {module.is_installed && (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                            <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                            Installed
                        </span>
                    )}
                </div>

                <div className="flex flex-wrap gap-1.5 mb-2">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}`}>
                        {module.type}
                    </span>
                    {module.language && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            {module.language}
                        </span>
                    )}
                    {module.version && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            v{module.version}
                        </span>
                    )}
                    {module.is_bundled && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                            Bundled
                        </span>
                    )}
                </div>

                {module.description && (
                    <p className="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                        {module.description}
                    </p>
                )}
            </div>

            {/* Actions */}
            <div className="border-t border-gray-100 dark:border-gray-800 px-4 py-3 flex items-center justify-between">
                <button
                    onClick={onDetails}
                    className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    Details
                </button>
                <div className="flex items-center gap-2">
                    {module.is_installed ? (
                        <Button variant="ghost" size="sm" onClick={onUninstall}>
                            Remove
                        </Button>
                    ) : (
                        <Button
                            variant="primary"
                            size="sm"
                            loading={isInstalling}
                            disabled={isInstalling}
                            onClick={onInstall}
                        >
                            {isInstalling ? 'Installing...' : 'Install'}
                        </Button>
                    )}
                </div>
            </div>
        </Card>
    );
}

function ModuleDetails({ module }: { module: Module }) {
    const rows: [string, string | null][] = [
        ['Key', module.key],
        ['Type', module.type],
        ['Language', module.language],
        ['Version', module.version],
        ['Driver', module.mod_drv],
        ['Encoding', module.encoding],
        ['Direction', module.direction],
        ['Category', module.category],
        ['Bundled', module.is_bundled ? 'Yes' : 'No'],
        ['Installed', module.is_installed ? 'Yes' : 'No'],
        ['File Size', module.file_size ? formatBytes(module.file_size) : null],
        ['Install Size', module.install_size ? formatBytes(module.install_size) : null],
    ];

    return (
        <div className="space-y-4">
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
                {rows.filter(([, v]) => v !== null).map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">{label}</dt>
                        <dd className="text-sm text-gray-900 dark:text-gray-100">{value}</dd>
                    </div>
                ))}
            </dl>
            {module.about && (
                <div>
                    <h4 className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">About</h4>
                    <p className="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                        {module.about}
                    </p>
                </div>
            )}
        </div>
    );
}

function StatCard({
    label,
    value,
    active,
    onClick,
    color = 'indigo',
}: {
    label: string;
    value: number;
    active: boolean;
    onClick: () => void;
    color?: string;
}) {
    const colors: Record<string, string> = {
        indigo: active ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30' : '',
        green: active ? 'border-green-500 bg-green-50 dark:bg-green-950/30' : '',
        blue: active ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : '',
    };

    return (
        <button
            onClick={onClick}
            className={`rounded-xl border p-4 text-left transition-all hover:shadow-md ${
                active
                    ? colors[color]
                    : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-700'
            }`}
        >
            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">{value}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">{label}</div>
        </button>
    );
}

function SearchIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
    );
}

function RefreshIcon() {
    return (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M2.985 19.644l3.181-3.18" />
        </svg>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
