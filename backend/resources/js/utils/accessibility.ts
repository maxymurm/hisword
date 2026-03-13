import { useCallback, useEffect, useRef } from 'react';

/**
 * Accessibility utilities for HisWord web application.
 * Ensures WCAG 2.1 AA compliance across all components.
 */

// ── Focus Management ─────────────────────────────────────────

/**
 * Trap focus within a container (for modals, dialogs, drawers).
 * Returns a ref to attach to the container element.
 */
export function useFocusTrap<T extends HTMLElement = HTMLElement>() {
    const containerRef = useRef<T>(null);

    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        const focusableElements = getFocusableElements(container);
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        // Focus first element on mount
        firstFocusable?.focus();

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable?.focus();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable?.focus();
                }
            }
        };

        container.addEventListener('keydown', handleKeyDown);
        return () => container.removeEventListener('keydown', handleKeyDown);
    }, []);

    return containerRef;
}

/**
 * Announce a message to screen readers using an ARIA live region.
 */
export function useAnnounce() {
    const announce = useCallback((message: string, priority: 'polite' | 'assertive' = 'polite') => {
        const el = document.getElementById(`sr-announce-${priority}`);
        if (el) {
            el.textContent = '';
            // Force screen reader to re-read by clearing then setting
            requestAnimationFrame(() => {
                el.textContent = message;
            });
        }
    }, []);

    return announce;
}

/**
 * Restore focus to the previously focused element (e.g., after closing a modal).
 */
export function useRestoreFocus() {
    const previousFocus = useRef<HTMLElement | null>(null);

    const saveFocus = useCallback(() => {
        previousFocus.current = document.activeElement as HTMLElement;
    }, []);

    const restoreFocus = useCallback(() => {
        previousFocus.current?.focus();
    }, []);

    return { saveFocus, restoreFocus };
}

/**
 * Skip to main content link handler.
 */
export function useSkipToContent(mainId: string = 'main-content') {
    const skipToContent = useCallback(() => {
        const main = document.getElementById(mainId);
        if (main) {
            main.setAttribute('tabindex', '-1');
            main.focus();
            main.removeAttribute('tabindex');
        }
    }, [mainId]);

    return skipToContent;
}

// ── Keyboard Navigation ──────────────────────────────────────

/**
 * Handle roving tabindex for list/grid navigation.
 * Arrow keys move between items, Home/End jump to first/last.
 */
export function useRovingTabindex(
    itemCount: number,
    orientation: 'horizontal' | 'vertical' | 'both' = 'vertical'
) {
    const activeIndex = useRef(0);

    const handleKeyDown = useCallback((e: React.KeyboardEvent, index: number) => {
        const prevKey = orientation === 'horizontal' ? 'ArrowLeft' : 'ArrowUp';
        const nextKey = orientation === 'horizontal' ? 'ArrowRight' : 'ArrowDown';

        let newIndex = index;

        switch (e.key) {
            case prevKey:
            case (orientation === 'both' ? 'ArrowLeft' : ''):
                e.preventDefault();
                newIndex = Math.max(0, index - 1);
                break;
            case nextKey:
            case (orientation === 'both' ? 'ArrowRight' : ''):
                e.preventDefault();
                newIndex = Math.min(itemCount - 1, index + 1);
                break;
            case 'Home':
                e.preventDefault();
                newIndex = 0;
                break;
            case 'End':
                e.preventDefault();
                newIndex = itemCount - 1;
                break;
            default:
                return;
        }

        activeIndex.current = newIndex;

        // Focus the new element
        const container = (e.target as HTMLElement).closest('[role]');
        const items = container?.querySelectorAll('[role="tab"], [role="option"], [role="menuitem"], [data-roving]');
        (items?.[newIndex] as HTMLElement)?.focus();
    }, [itemCount, orientation]);

    const getTabIndex = useCallback((index: number) => {
        return index === activeIndex.current ? 0 : -1;
    }, []);

    return { handleKeyDown, getTabIndex };
}

// ── Motion Preferences ───────────────────────────────────────

/**
 * Detect if the user prefers reduced motion.
 */
export function usePrefersReducedMotion(): boolean {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Get animation duration based on user's motion preferences.
 * Returns 0 if reduced motion is preferred.
 */
export function getAnimationDuration(defaultMs: number = 200): number {
    if (typeof window === 'undefined') return defaultMs;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : defaultMs;
}

// ── Color Contrast ───────────────────────────────────────────

/**
 * Check if two colors meet WCAG AA contrast ratio (4.5:1 for normal text, 3:1 for large text).
 */
export function meetsContrastRatio(
    foreground: string,
    background: string,
    level: 'AA' | 'AAA' = 'AA',
    isLargeText: boolean = false
): boolean {
    const ratio = getContrastRatio(foreground, background);
    if (level === 'AAA') return isLargeText ? ratio >= 4.5 : ratio >= 7;
    return isLargeText ? ratio >= 3 : ratio >= 4.5;
}

function getContrastRatio(color1: string, color2: string): number {
    const l1 = getRelativeLuminance(color1);
    const l2 = getRelativeLuminance(color2);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
}

function getRelativeLuminance(hex: string): number {
    const rgb = hexToRgb(hex);
    if (!rgb) return 0;
    const [r, g, b] = [rgb.r, rgb.g, rgb.b].map(c => {
        c = c / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16),
    } : null;
}

// ── Helpers ──────────────────────────────────────────────────

function getFocusableElements(container: HTMLElement): HTMLElement[] {
    const selector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
        '[contenteditable]',
    ].join(', ');

    return Array.from(container.querySelectorAll<HTMLElement>(selector));
}

// ── ARIA Live Region Component Props ─────────────────────────

/**
 * Props for the ScreenReaderAnnouncer component.
 * Include this component once at the app root.
 */
export const screenReaderAnnouncerProps = {
    polite: {
        id: 'sr-announce-polite',
        role: 'status' as const,
        'aria-live': 'polite' as const,
        'aria-atomic': true,
        className: 'sr-only',
    },
    assertive: {
        id: 'sr-announce-assertive',
        role: 'alert' as const,
        'aria-live': 'assertive' as const,
        'aria-atomic': true,
        className: 'sr-only',
    },
};

// ── Verse Accessibility ──────────────────────────────────────

/**
 * Generate accessible label for a Bible verse.
 * Includes proper pauses for screen reader flow.
 */
export function verseAccessibleLabel(
    bookName: string,
    chapter: number,
    verseNumber: number,
    text: string
): string {
    // Strip HTML tags for screen reader
    const cleanText = text.replace(/<[^>]*>/g, '');
    return `${bookName} chapter ${chapter}, verse ${verseNumber}. ${cleanText}`;
}

/**
 * Generate accessible label for a verse range.
 */
export function verseRangeLabel(
    bookName: string,
    chapter: number,
    startVerse: number,
    endVerse: number
): string {
    if (startVerse === endVerse) {
        return `${bookName} ${chapter}:${startVerse}`;
    }
    return `${bookName} ${chapter}:${startVerse} through ${endVerse}`;
}
