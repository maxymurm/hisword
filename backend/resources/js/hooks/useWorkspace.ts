import { useState, useCallback, useEffect } from 'react';

export interface Tab {
    id: string;
    title: string;
    url: string;
    type: 'reader' | 'search' | 'notes' | 'commentary' | 'dictionary' | 'other';
}

export interface Layout {
    name: string;
    tabs: Tab[];
    splitMode: 'single' | 'horizontal' | 'vertical';
    activeTabId: string | null;
}

const STORAGE_KEY = 'HisWord-workspace';

function loadLayout(): Layout {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) return JSON.parse(raw);
    } catch { /* ignore */ }
    return { name: 'Default', tabs: [], splitMode: 'single', activeTabId: null };
}

function saveLayout(layout: Layout) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(layout));
}

export function useWorkspace() {
    const [layout, setLayout] = useState<Layout>(loadLayout);

    useEffect(() => { saveLayout(layout); }, [layout]);

    const addTab = useCallback((tab: Omit<Tab, 'id'>) => {
        const id = `tab-${Date.now()}`;
        setLayout(prev => ({
            ...prev,
            tabs: [...prev.tabs, { ...tab, id }],
            activeTabId: id,
        }));
        return id;
    }, []);

    const removeTab = useCallback((id: string) => {
        setLayout(prev => {
            const tabs = prev.tabs.filter(t => t.id !== id);
            return {
                ...prev,
                tabs,
                activeTabId: prev.activeTabId === id ? (tabs[0]?.id ?? null) : prev.activeTabId,
            };
        });
    }, []);

    const setActiveTab = useCallback((id: string) => {
        setLayout(prev => ({ ...prev, activeTabId: id }));
    }, []);

    const reorderTabs = useCallback((fromIndex: number, toIndex: number) => {
        setLayout(prev => {
            const tabs = [...prev.tabs];
            const [moved] = tabs.splice(fromIndex, 1);
            tabs.splice(toIndex, 0, moved);
            return { ...prev, tabs };
        });
    }, []);

    const setSplitMode = useCallback((mode: Layout['splitMode']) => {
        setLayout(prev => ({ ...prev, splitMode: mode }));
    }, []);

    const activeTab = layout.tabs.find(t => t.id === layout.activeTabId) ?? null;

    return {
        layout,
        tabs: layout.tabs,
        activeTab,
        addTab,
        removeTab,
        setActiveTab,
        reorderTabs,
        setSplitMode,
    };
}
