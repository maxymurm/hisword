import { useState, useEffect } from 'react';
import { getShortcuts } from '@/hooks/useKeyboardShortcuts';

export default function ShortcutsModal() {
    const [open, setOpen] = useState(false);
    const shortcuts = getShortcuts();

    useEffect(() => {
        function handler(e: KeyboardEvent) {
            if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
                const target = e.target as HTMLElement;
                if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return;
                e.preventDefault();
                setOpen(o => !o);
            }
            if (e.key === 'Escape' && open) {
                setOpen(false);
            }
        }
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [open]);

    if (!open) return null;

    const isMac = navigator.platform.toUpperCase().includes('MAC');
    const mod = isMac ? '⌘' : 'Ctrl';

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50" onClick={() => setOpen(false)}>
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4 p-6" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">Keyboard Shortcuts</h2>
                    <button onClick={() => setOpen(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-xl">&times;</button>
                </div>
                <div className="space-y-2">
                    {shortcuts.map(s => (
                        <div key={s.key} className="flex items-center justify-between py-1.5">
                            <span className="text-gray-700 dark:text-gray-300">{s.description}</span>
                            <kbd className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded text-sm font-mono border border-gray-200 dark:border-gray-600">
                                {s.ctrl ? `${mod}+${s.key.toUpperCase()}` : s.key.toUpperCase()}
                            </kbd>
                        </div>
                    ))}
                    <div className="flex items-center justify-between py-1.5 border-t border-gray-200 dark:border-gray-700 mt-2 pt-3">
                        <span className="text-gray-700 dark:text-gray-300">Show this help</span>
                        <kbd className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded text-sm font-mono border border-gray-200 dark:border-gray-600">?</kbd>
                    </div>
                </div>
            </div>
        </div>
    );
}
