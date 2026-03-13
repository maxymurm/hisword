import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Debounce a value – useful for search inputs to avoid excessive API calls.
 */
export function useDebounce<T>(value: T, delayMs: number = 300): T {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedValue(value), delayMs);
        return () => clearTimeout(timer);
    }, [value, delayMs]);

    return debouncedValue;
}

/**
 * Intersection Observer hook for lazy-loading content when it enters the viewport.
 */
export function useInView(options?: IntersectionObserverInit) {
    const [isInView, setIsInView] = useState(false);
    const ref = useRef<HTMLElement | null>(null);

    useEffect(() => {
        const element = ref.current;
        if (!element) return;

        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setIsInView(true);
                observer.unobserve(element);
            }
        }, { threshold: 0.1, ...options });

        observer.observe(element);
        return () => observer.disconnect();
    }, []);

    return { ref, isInView };
}

/**
 * Virtual scroll helper – only render items in the viewport + buffer.
 */
export function useVirtualScroll<T>(
    items: T[],
    itemHeight: number,
    containerHeight: number,
    overscan: number = 5
) {
    const [scrollTop, setScrollTop] = useState(0);

    const startIndex = Math.max(0, Math.floor(scrollTop / itemHeight) - overscan);
    const endIndex = Math.min(
        items.length,
        Math.ceil((scrollTop + containerHeight) / itemHeight) + overscan
    );

    const visibleItems = items.slice(startIndex, endIndex);
    const totalHeight = items.length * itemHeight;
    const offsetY = startIndex * itemHeight;

    const onScroll = useCallback((e: React.UIEvent<HTMLElement>) => {
        setScrollTop(e.currentTarget.scrollTop);
    }, []);

    return { visibleItems, totalHeight, offsetY, onScroll, startIndex };
}

/**
 * Prefetch a route on hover/focus for faster navigation.
 */
export function usePrefetch() {
    const prefetched = useRef(new Set<string>());

    const prefetch = useCallback((url: string) => {
        if (prefetched.current.has(url)) return;
        prefetched.current.add(url);

        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
    }, []);

    return prefetch;
}

/**
 * Measure and report Web Vitals (CLS, FID, LCP, FCP, TTFB).
 */
export function reportWebVitals(onReport?: (metric: { name: string; value: number; rating: string }) => void) {
    if (typeof window === 'undefined') return;

    const report = onReport ?? ((metric) => {
        if (import.meta.env.DEV) {
            console.log(`[Web Vital] ${metric.name}: ${metric.value.toFixed(2)} (${metric.rating})`);
        }
    });

    // LCP - Largest Contentful Paint
    if ('PerformanceObserver' in window) {
        try {
            const lcpObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1] as any;
                report({
                    name: 'LCP',
                    value: lastEntry.startTime,
                    rating: lastEntry.startTime <= 2500 ? 'good' : lastEntry.startTime <= 4000 ? 'needs-improvement' : 'poor',
                });
            });
            lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
        } catch { /* Observer not supported */ }

        // CLS - Cumulative Layout Shift
        try {
            let clsValue = 0;
            const clsObserver = new PerformanceObserver((list) => {
                for (const entry of list.getEntries() as any[]) {
                    if (!entry.hadRecentInput) {
                        clsValue += entry.value;
                    }
                }
                report({
                    name: 'CLS',
                    value: clsValue,
                    rating: clsValue <= 0.1 ? 'good' : clsValue <= 0.25 ? 'needs-improvement' : 'poor',
                });
            });
            clsObserver.observe({ type: 'layout-shift', buffered: true });
        } catch { /* Observer not supported */ }

        // FID - First Input Delay
        try {
            const fidObserver = new PerformanceObserver((list) => {
                const entry = list.getEntries()[0] as any;
                const fid = entry.processingStart - entry.startTime;
                report({
                    name: 'FID',
                    value: fid,
                    rating: fid <= 100 ? 'good' : fid <= 300 ? 'needs-improvement' : 'poor',
                });
            });
            fidObserver.observe({ type: 'first-input', buffered: true });
        } catch { /* Observer not supported */ }
    }

    // TTFB - Time to First Byte
    const navEntry = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
    if (navEntry) {
        const ttfb = navEntry.responseStart - navEntry.requestStart;
        report({
            name: 'TTFB',
            value: ttfb,
            rating: ttfb <= 800 ? 'good' : ttfb <= 1800 ? 'needs-improvement' : 'poor',
        });
    }
}
