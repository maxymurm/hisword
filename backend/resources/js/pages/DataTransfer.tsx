import { useState, useRef } from 'react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
    counts: { bookmarks: number; highlights: number; notes: number; pins: number };
}

interface ImportPreview {
    version: string;
    exported_at: string | null;
    counts: { bookmarks: number; highlights: number; notes: number; pins: number };
}

interface ImportResult {
    bookmarks: number;
    highlights: number;
    notes: number;
    pins: number;
}

export default function DataTransfer({ counts }: Props) {
    const [importing, setImporting] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [preview, setPreview] = useState<ImportPreview | null>(null);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const [error, setError] = useState('');
    const fileRef = useRef<HTMLInputElement>(null);
    const selectedFile = useRef<File | null>(null);

    const totalItems = counts.bookmarks + counts.highlights + counts.notes + counts.pins;

    function handleExport() {
        setExporting(true);
        fetch('/data-transfer/export')
            .then(r => r.json())
            .then(data => {
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `HisWord-backup-${new Date().toISOString().split('T')[0]}.json`;
                a.click();
                URL.revokeObjectURL(url);
                setExporting(false);
            })
            .catch(() => { setError('Export failed.'); setExporting(false); });
    }

    function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        selectedFile.current = file;
        setError('');
        setImportResult(null);

        const formData = new FormData();
        formData.append('file', file);

        fetch('/data-transfer/preview', { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' } })
            .then(r => r.json())
            .then(data => {
                if (data.error) { setError(data.error); return; }
                setPreview(data);
            })
            .catch(() => setError('Failed to read file.'));
    }

    function handleImport() {
        if (!selectedFile.current) return;
        setImporting(true);

        const formData = new FormData();
        formData.append('file', selectedFile.current);

        fetch('/data-transfer/import', { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' } })
            .then(r => r.json())
            .then(data => {
                if (data.error) { setError(data.error); setImporting(false); return; }
                setImportResult(data.imported);
                setPreview(null);
                setImporting(false);
            })
            .catch(() => { setError('Import failed.'); setImporting(false); });
    }

    return (
        <AppLayout title="Data Transfer">
            <div className="max-w-3xl mx-auto px-4 py-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Data Transfer</h1>
                <p className="text-gray-500 dark:text-gray-400 mb-8">Export or import your annotations and study data.</p>

                {error && (
                    <div className="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400">{error}</div>
                )}

                {importResult && (
                    <div className="mb-6 p-4 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400">
                        Import complete: {importResult.bookmarks} bookmarks, {importResult.highlights} highlights, {importResult.notes} notes, {importResult.pins} pins.
                    </div>
                )}

                <div className="grid md:grid-cols-2 gap-6">
                    {/* Export */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <div className="text-3xl mb-3">📤</div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">Export Data</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Download all your annotations as a JSON file.</p>
                        <div className="text-sm text-gray-600 dark:text-gray-300 mb-4 space-y-1">
                            <div>{counts.bookmarks} bookmarks</div>
                            <div>{counts.highlights} highlights</div>
                            <div>{counts.notes} notes</div>
                            <div>{counts.pins} pins</div>
                            <div className="font-medium pt-1 border-t border-gray-200 dark:border-gray-700">{totalItems} total items</div>
                        </div>
                        <button
                            onClick={handleExport}
                            disabled={exporting || totalItems === 0}
                            className="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 font-medium"
                        >
                            {exporting ? 'Exporting...' : 'Export All Data'}
                        </button>
                    </div>

                    {/* Import */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <div className="text-3xl mb-3">📥</div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">Import Data</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Restore from a previously exported JSON file.</p>

                        <input
                            ref={fileRef}
                            type="file"
                            accept=".json"
                            onChange={handleFileSelect}
                            className="hidden"
                        />
                        <button
                            onClick={() => fileRef.current?.click()}
                            className="w-full py-2 mb-3 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium"
                        >
                            Select File
                        </button>

                        {preview && (
                            <div className="text-sm text-gray-600 dark:text-gray-300 mb-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <div className="font-medium mb-2">Preview:</div>
                                <div>{preview.counts.bookmarks} bookmarks</div>
                                <div>{preview.counts.highlights} highlights</div>
                                <div>{preview.counts.notes} notes</div>
                                <div>{preview.counts.pins} pins</div>
                                {preview.exported_at && <div className="text-xs text-gray-400 mt-2">Exported: {new Date(preview.exported_at).toLocaleDateString()}</div>}
                                <button
                                    onClick={handleImport}
                                    disabled={importing}
                                    className="w-full mt-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium"
                                >
                                    {importing ? 'Importing...' : 'Confirm Import'}
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
