import { useState } from 'react';

interface AdvancedSearchProps {
    onSearch: (query: string) => void;
    modules: { key: string; name: string }[];
}

export default function AdvancedSearchPanel({ onSearch, modules }: AdvancedSearchProps) {
    const [allWords, setAllWords] = useState('');
    const [exactPhrase, setExactPhrase] = useState('');
    const [anyWords, setAnyWords] = useState('');
    const [excludeWords, setExcludeWords] = useState('');
    const [proximity, setProximity] = useState('');
    const [proximityDistance, setProximityDistance] = useState(5);

    const buildQuery = () => {
        const parts: string[] = [];

        if (exactPhrase.trim()) {
            parts.push(`"${exactPhrase.trim()}"`);
        }
        if (allWords.trim()) {
            parts.push(allWords.trim());
        }
        if (anyWords.trim()) {
            const words = anyWords.trim().split(/\s+/);
            if (words.length > 1) {
                parts.push(words.join(' OR '));
            } else {
                parts.push(words[0]);
            }
        }
        if (excludeWords.trim()) {
            excludeWords.trim().split(/\s+/).forEach(w => {
                parts.push(`-${w}`);
            });
        }
        if (proximity.trim()) {
            const words = proximity.trim().split(/\s+/);
            if (words.length >= 2) {
                parts.push(`NEAR(${words.join(' ')}, ${proximityDistance})`);
            }
        }

        return parts.join(' ');
    };

    const handleSearch = () => {
        const q = buildQuery();
        if (q) onSearch(q);
    };

    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 mb-6 space-y-4">
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">
                Advanced Search
            </h3>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="All of these words"
                    placeholder="love mercy grace"
                    value={allWords}
                    onChange={setAllWords}
                />
                <Field
                    label="This exact phrase"
                    placeholder="the Lord is my shepherd"
                    value={exactPhrase}
                    onChange={setExactPhrase}
                />
                <Field
                    label="Any of these words"
                    placeholder="faith hope love"
                    value={anyWords}
                    onChange={setAnyWords}
                />
                <Field
                    label="None of these words"
                    placeholder="death curse"
                    value={excludeWords}
                    onChange={setExcludeWords}
                />
            </div>

            <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    Proximity search (words near each other)
                </label>
                <div className="flex gap-2">
                    <input
                        type="text"
                        placeholder="faith works"
                        value={proximity}
                        onChange={e => setProximity(e.target.value)}
                        className="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
                    />
                    <div className="flex items-center gap-1">
                        <span className="text-xs text-gray-500">within</span>
                        <input
                            type="number"
                            min={1}
                            max={50}
                            value={proximityDistance}
                            onChange={e => setProximityDistance(parseInt(e.target.value, 10) || 5)}
                            className="w-16 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-2 text-sm text-center"
                        />
                        <span className="text-xs text-gray-500">words</span>
                    </div>
                </div>
            </div>

            <div className="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                <button
                    onClick={() => {
                        setAllWords(''); setExactPhrase(''); setAnyWords('');
                        setExcludeWords(''); setProximity(''); setProximityDistance(5);
                    }}
                    className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                >
                    Clear All
                </button>
                <button
                    onClick={handleSearch}
                    className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
                >
                    Search
                </button>
            </div>

            {/* Search syntax help */}
            <details className="text-xs text-gray-500 dark:text-gray-400">
                <summary className="cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">
                    Search syntax reference
                </summary>
                <div className="mt-2 space-y-1 pl-4">
                    <p><code className="text-indigo-600 dark:text-indigo-400">"exact phrase"</code> — Match exact sequence of words</p>
                    <p><code className="text-indigo-600 dark:text-indigo-400">word1 OR word2</code> — Match either word</p>
                    <p><code className="text-indigo-600 dark:text-indigo-400">-word</code> — Exclude results containing word</p>
                    <p><code className="text-indigo-600 dark:text-indigo-400">NEAR(word1 word2, 5)</code> — Words within 5 words of each other</p>
                    <p><code className="text-indigo-600 dark:text-indigo-400">love*</code> — Wildcard: love, loved, lovely, etc.</p>
                </div>
            </details>
        </div>
    );
}

function Field({
    label,
    placeholder,
    value,
    onChange,
}: {
    label: string;
    placeholder: string;
    value: string;
    onChange: (v: string) => void;
}) {
    return (
        <div>
            <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                {label}
            </label>
            <input
                type="text"
                placeholder={placeholder}
                value={value}
                onChange={e => onChange(e.target.value)}
                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
            />
        </div>
    );
}
