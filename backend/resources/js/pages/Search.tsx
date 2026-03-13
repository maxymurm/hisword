import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import AdvancedSearchPanel from '@/components/AdvancedSearchPanel';

// ── Types ───────────────────────────────────────────────────────

interface ModuleOption {
    id: string;
    key: string;
    name: string;
    language: string;
}

interface BookOption {
    osis_id: string;
    name: string;
    testament: string;
}

interface SearchHit {
    id: number;
    reference: string;
    book_osis_id: string;
    book_name: string;
    chapter_number: number;
    verse_number: number;
    text: string;
    highlight: string;
    module_key: string;
    module_name: string;
}

interface SearchMeta {
    query: string;
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

interface SearchResults {
    hits: SearchHit[];
    meta: SearchMeta;
}

interface Props {
    modules: ModuleOption[];
    books: BookOption[];
    initialQuery: string;
    initialResults: SearchResults | null;
}

// ── Recent Searches ─────────────────────────────────────────────

function getRecentSearches(): string[] {
    if (typeof window === 'undefined') return [];
    try {
        return JSON.parse(localStorage.getItem('recent_searches') || '[]');
    } catch { return []; }
}

function saveRecentSearch(q: string) {
    const recent = getRecentSearches().filter(s => s !== q);
    recent.unshift(q);
    localStorage.setItem('recent_searches', JSON.stringify(recent.slice(0, 10)));
}

// ── Main Component ──────────────────────────────────────────────

export default function Search({ modules, books, initialQuery, initialResults }: Props) {
    const [query, setQuery] = useState(initialQuery);
    const [moduleFilter, setModuleFilter] = useState('');
    const [scopeFilter, setScopeFilter] = useState('all');
    const [bookFilter, setBookFilter] = useState('');
    const [results, setResults] = useState<SearchResults | null>(initialResults);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(initialResults?.meta.current_page ?? 1);
    const [recentSearches, setRecentSearches] = useState<string[]>(getRecentSearches);
    const [showRecent, setShowRecent] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);

    // Ctrl+K to focus search
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    // Perform search
    const doSearch = useCallback(async (q: string, mod: string, scope: string, book: string, pg: number) => {
        if (q.length < 2) {
            setResults(null);
            return;
        }
        setLoading(true);
        try {
            const params = new URLSearchParams({ q, page: String(pg) });
            if (mod) params.set('module', mod);
            if (scope !== 'all') params.set('scope', scope);
            if (scope === 'book' && book) params.set('book', book);

            const res = await fetch(`/search/query?${params}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data: SearchResults = await res.json();
                setResults(data);
                setPage(data.meta.current_page);
                // Update URL without full page reload
                const url = new URL(window.location.href);
                url.searchParams.set('q', q);
                window.history.replaceState({}, '', url.toString());
                saveRecentSearch(q);
                setRecentSearches(getRecentSearches());
            }
        } finally {
            setLoading(false);
        }
    }, []);

    // Debounced search on query change
    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            doSearch(query, moduleFilter, scopeFilter, bookFilter, 1);
        }, 300);
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
    }, [query, moduleFilter, scopeFilter, bookFilter, doSearch]);

    const changePage = (newPage: number) => {
        doSearch(query, moduleFilter, scopeFilter, bookFilter, newPage);
        window.scrollTo(0, 0);
    };

    const otBooks = books.filter(b => b.testament === 'OT');
    const ntBooks = books.filter(b => b.testament === 'NT');

    return (
        <AppLayout title="Search">
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                {/* Search Input */}
                <div className="relative mb-6">
                    <div className="relative">
                        <svg className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={e => setQuery(e.target.value)}
                            onFocus={() => setShowRecent(true)}
                            onBlur={() => setTimeout(() => setShowRecent(false), 200)}
                            placeholder="Search the Bible... (Ctrl+K)"
                            className="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 pl-12 pr-4 py-3 text-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent shadow-sm"
                            autoFocus
                        />
                        {loading && (
                            <div className="absolute right-4 top-1/2 -translate-y-1/2">
                                <div className="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600" />
                            </div>
                        )}
                    </div>

                    {/* Recent searches dropdown */}
                    {showRecent && !query && recentSearches.length > 0 && (
                        <div className="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg">
                            <div className="px-3 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Recent Searches
                            </div>
                            {recentSearches.map((s, i) => (
                                <button
                                    key={i}
                                    onMouseDown={() => setQuery(s)}
                                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                >
                                    <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {s}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Advanced toggle */}
                <div className="flex justify-end mb-2">
                    <button
                        onClick={() => setShowAdvanced(!showAdvanced)}
                        className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        {showAdvanced ? 'Simple Search' : 'Advanced Search'}
                    </button>
                </div>

                {showAdvanced && (
                    <AdvancedSearchPanel
                        modules={modules}
                        onSearch={(q) => { setQuery(q); setShowAdvanced(false); }}
                    />
                )}

                {/* Filters */}
                <div className="flex flex-wrap gap-3 mb-6">
                    <select
                        value={moduleFilter}
                        onChange={e => setModuleFilter(e.target.value)}
                        className="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                    >
                        <option value="">All Modules</option>
                        {modules.map(m => (
                            <option key={m.key} value={m.key}>{m.name} ({m.key})</option>
                        ))}
                    </select>

                    <select
                        value={scopeFilter}
                        onChange={e => { setScopeFilter(e.target.value); setBookFilter(''); }}
                        className="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                    >
                        <option value="all">Entire Bible</option>
                        <option value="ot">Old Testament</option>
                        <option value="nt">New Testament</option>
                        <option value="book">Specific Book</option>
                    </select>

                    {scopeFilter === 'book' && (
                        <select
                            value={bookFilter}
                            onChange={e => setBookFilter(e.target.value)}
                            className="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                        >
                            <option value="">Select Book</option>
                            <optgroup label="Old Testament">
                                {otBooks.map(b => (
                                    <option key={b.osis_id} value={b.osis_id}>{b.name}</option>
                                ))}
                            </optgroup>
                            <optgroup label="New Testament">
                                {ntBooks.map(b => (
                                    <option key={b.osis_id} value={b.osis_id}>{b.name}</option>
                                ))}
                            </optgroup>
                        </select>
                    )}
                </div>

                {/* Result count */}
                {results && (
                    <div className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {results.meta.total === 0
                            ? `No results for "${results.meta.query}"`
                            : `${results.meta.total.toLocaleString()} result${results.meta.total === 1 ? '' : 's'} for "${results.meta.query}"`
                        }
                    </div>
                )}

                {/* Results */}
                {results && results.hits.length > 0 && (
                    <div className="space-y-3">
                        {results.hits.map(hit => (
                            <Link
                                key={`${hit.module_key}-${hit.id}`}
                                href={`/read/${hit.module_key}/${hit.book_osis_id}/${hit.chapter_number}`}
                                className="block rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-5 py-4 shadow-sm hover:shadow-md transition-shadow group"
                            >
                                <div className="flex items-center justify-between mb-1.5">
                                    <span className="text-sm font-semibold text-indigo-600 dark:text-indigo-400 group-hover:underline">
                                        {hit.reference}
                                    </span>
                                    <span className="text-xs text-gray-400 dark:text-gray-500 font-medium">
                                        {hit.module_key}
                                    </span>
                                </div>
                                <div
                                    className="text-sm text-gray-700 dark:text-gray-300 line-clamp-2 [&_mark]:bg-yellow-200 dark:[&_mark]:bg-yellow-800/50 [&_mark]:rounded [&_mark]:px-0.5"
                                    dangerouslySetInnerHTML={{ __html: hit.highlight }}
                                />
                            </Link>
                        ))}
                    </div>
                )}

                {/* No results state */}
                {results && results.hits.length === 0 && (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <svg className="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <p className="text-lg font-medium">No results found</p>
                        <p className="text-sm mt-1">Try different keywords or broaden your filters</p>
                    </div>
                )}

                {/* Empty state */}
                {!results && !query && (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <svg className="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <p className="text-lg font-medium">Search the Bible</p>
                        <p className="text-sm mt-1">Enter a word or phrase to search across all installed modules</p>
                    </div>
                )}

                {/* Pagination */}
                {results && results.meta.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2 mt-8">
                        <button
                            onClick={() => changePage(page - 1)}
                            disabled={page <= 1}
                            className="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        >
                            Previous
                        </button>
                        <span className="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 tabular-nums">
                            Page {results.meta.current_page} of {results.meta.last_page}
                        </span>
                        <button
                            onClick={() => changePage(page + 1)}
                            disabled={page >= results.meta.last_page}
                            className="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        >
                            Next
                        </button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
