import { useEffect, useRef } from 'react';

/**
 * Trap focus within a container element (for modals, dialogs, drawers).
 * Returns a ref to attach to the container element.
 */
export function useFocusTrap<T extends HTMLElement = HTMLDivElement>(active = true) {
    const ref = useRef<T>(null);

    useEffect(() => {
        if (!active || !ref.current) return;

        const container = ref.current;
        const focusable = getFocusableElements(container);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        // Save previously focused element
        const previouslyFocused = document.activeElement as HTMLElement;

        // Focus first element
        first?.focus();

        function handleKeyDown(e: KeyboardEvent) {
            if (e.key !== 'Tab') return;

            const currentFocusable = getFocusableElements(container);
            const firstEl = currentFocusable[0];
            const lastEl = currentFocusable[currentFocusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === firstEl) {
                    e.preventDefault();
                    lastEl?.focus();
                }
            } else {
                if (document.activeElement === lastEl) {
                    e.preventDefault();
                    firstEl?.focus();
                }
            }
        }

        container.addEventListener('keydown', handleKeyDown);

        return () => {
            container.removeEventListener('keydown', handleKeyDown);
            previouslyFocused?.focus();
        };
    }, [active]);

    return ref;
}

function getFocusableElements(container: HTMLElement): HTMLElement[] {
    const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return Array.from(container.querySelectorAll<HTMLElement>(selector));
}

/**
 * Announce a message to screen readers via the ARIA live region.
 */
export function announce(message: string, priority: 'polite' | 'assertive' = 'polite') {
    const region = document.getElementById(`sr-${priority}`);
    if (region) {
        region.textContent = '';
        requestAnimationFrame(() => { region.textContent = message; });
    }
}

/**
 * Hook to manage roving tabindex for arrow-key navigation in groups
 * (e.g., tab lists, toolbars, radio groups).
 */
export function useRovingTabIndex(items: HTMLElement[], vertical = false) {
    useEffect(() => {
        if (items.length === 0) return;

        items.forEach((el, i) => {
            el.setAttribute('tabindex', i === 0 ? '0' : '-1');
        });

        function handleKeyDown(e: KeyboardEvent) {
            const currentIndex = items.indexOf(e.target as HTMLElement);
            if (currentIndex === -1) return;

            const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';
            const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
            let newIndex = currentIndex;

            if (e.key === nextKey) {
                newIndex = (currentIndex + 1) % items.length;
            } else if (e.key === prevKey) {
                newIndex = (currentIndex - 1 + items.length) % items.length;
            } else if (e.key === 'Home') {
                newIndex = 0;
            } else if (e.key === 'End') {
                newIndex = items.length - 1;
            } else {
                return;
            }

            e.preventDefault();
            items[currentIndex].setAttribute('tabindex', '-1');
            items[newIndex].setAttribute('tabindex', '0');
            items[newIndex].focus();
        }

        items.forEach(el => el.addEventListener('keydown', handleKeyDown));
        return () => items.forEach(el => el.removeEventListener('keydown', handleKeyDown));
    }, [items, vertical]);
}
