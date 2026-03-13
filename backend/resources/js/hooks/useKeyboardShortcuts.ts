import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export interface Shortcut {
    key: string;
    ctrl?: boolean;
    shift?: boolean;
    description: string;
    action: () => void;
}

const globalShortcuts: Shortcut[] = [
    { key: 'b', ctrl: true, description: 'Bookmarks', action: () => router.visit('/bookmarks') },
    { key: 'h', ctrl: true, description: 'Highlights', action: () => router.visit('/highlights') },
    { key: 'n', ctrl: true, description: 'Notes', action: () => router.visit('/notes') },
    { key: 'k', ctrl: true, description: 'Study Pad', action: () => router.visit('/study-pad') },
    { key: 'p', ctrl: true, description: 'Reading Plans', action: () => router.visit('/reading-plans') },
    { key: 'f', ctrl: true, description: 'Search', action: () => router.visit('/search') },
    { key: 'm', ctrl: true, description: 'Modules', action: () => router.visit('/modules') },
    { key: ',', ctrl: true, description: 'Settings', action: () => router.visit('/settings') },
];

export function getShortcuts() {
    return globalShortcuts;
}

export function useKeyboardShortcuts(extra: Shortcut[] = []) {
    useEffect(() => {
        const all = [...globalShortcuts, ...extra];

        function handler(e: KeyboardEvent) {
            // Skip if user is typing in an input
            const target = e.target as HTMLElement;
            if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return;

            for (const s of all) {
                const ctrlMatch = s.ctrl ? (e.ctrlKey || e.metaKey) : !(e.ctrlKey || e.metaKey);
                const shiftMatch = s.shift ? e.shiftKey : !e.shiftKey;
                if (e.key.toLowerCase() === s.key.toLowerCase() && ctrlMatch && shiftMatch) {
                    e.preventDefault();
                    s.action();
                    return;
                }
            }
        }

        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [extra]);
}
