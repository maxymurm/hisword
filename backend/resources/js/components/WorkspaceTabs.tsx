import { useState, useRef } from 'react';
import type { Tab, Layout } from '@/hooks/useWorkspace';

interface Props {
    tabs: Tab[];
    activeTabId: string | null;
    splitMode: Layout['splitMode'];
    onSelect: (id: string) => void;
    onClose: (id: string) => void;
    onReorder: (from: number, to: number) => void;
    onSplitChange: (mode: Layout['splitMode']) => void;
}

const typeIcons: Record<Tab['type'], string> = {
    reader: '📖',
    search: '🔍',
    notes: '📝',
    commentary: '💬',
    dictionary: '📚',
    other: '📄',
};

export default function WorkspaceTabs({ tabs, activeTabId, splitMode, onSelect, onClose, onReorder, onSplitChange }: Props) {
    const [dragIndex, setDragIndex] = useState<number | null>(null);

    if (tabs.length === 0) return null;

    return (
        <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center gap-1 px-2 py-1 overflow-x-auto">
            {tabs.map((tab, i) => (
                <div
                    key={tab.id}
                    draggable
                    onDragStart={() => setDragIndex(i)}
                    onDragOver={e => { e.preventDefault(); }}
                    onDrop={() => { if (dragIndex !== null && dragIndex !== i) onReorder(dragIndex, i); setDragIndex(null); }}
                    onClick={() => onSelect(tab.id)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-t-lg text-sm cursor-pointer select-none whitespace-nowrap transition ${
                        tab.id === activeTabId
                            ? 'bg-gray-100 dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 font-medium'
                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'
                    }`}
                >
                    <span>{typeIcons[tab.type]}</span>
                    <span className="max-w-[120px] truncate">{tab.title}</span>
                    <button
                        onClick={e => { e.stopPropagation(); onClose(tab.id); }}
                        className="ml-1 text-gray-400 hover:text-red-500 text-xs"
                        aria-label={`Close ${tab.title}`}
                    >
                        ×
                    </button>
                </div>
            ))}

            <div className="ml-auto flex items-center gap-1 pl-2 border-l border-gray-200 dark:border-gray-700">
                {(['single', 'horizontal', 'vertical'] as const).map(mode => (
                    <button
                        key={mode}
                        onClick={() => onSplitChange(mode)}
                        className={`p-1 rounded text-xs ${splitMode === mode ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'}`}
                        title={mode === 'single' ? 'Single pane' : mode === 'horizontal' ? 'Split horizontal' : 'Split vertical'}
                    >
                        {mode === 'single' ? '◻' : mode === 'horizontal' ? '◫' : '⬒'}
                    </button>
                ))}
            </div>
        </div>
    );
}
