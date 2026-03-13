import { useEffect, useRef } from 'react';

interface UseSwipeOptions {
    onSwipeLeft?: () => void;
    onSwipeRight?: () => void;
    threshold?: number;
    enabled?: boolean;
}

/**
 * Hook to detect horizontal swipe gestures on touch devices.
 * Returns a ref to attach to the swipeable element.
 */
export function useSwipe<T extends HTMLElement = HTMLDivElement>({
    onSwipeLeft,
    onSwipeRight,
    threshold = 80,
    enabled = true,
}: UseSwipeOptions) {
    const ref = useRef<T>(null);
    const startX = useRef(0);
    const startY = useRef(0);

    useEffect(() => {
        if (!enabled) return;
        const el = ref.current;
        if (!el) return;

        const handleStart = (e: TouchEvent) => {
            startX.current = e.touches[0].clientX;
            startY.current = e.touches[0].clientY;
        };

        const handleEnd = (e: TouchEvent) => {
            const deltaX = e.changedTouches[0].clientX - startX.current;
            const deltaY = e.changedTouches[0].clientY - startY.current;

            // Only trigger if horizontal movement > vertical movement
            if (Math.abs(deltaX) < threshold || Math.abs(deltaX) < Math.abs(deltaY)) {
                return;
            }

            if (deltaX > 0) {
                onSwipeRight?.();
            } else {
                onSwipeLeft?.();
            }
        };

        el.addEventListener('touchstart', handleStart, { passive: true });
        el.addEventListener('touchend', handleEnd, { passive: true });

        return () => {
            el.removeEventListener('touchstart', handleStart);
            el.removeEventListener('touchend', handleEnd);
        };
    }, [enabled, onSwipeLeft, onSwipeRight, threshold]);

    return ref;
}
