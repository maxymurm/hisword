import { screenReaderAnnouncerProps } from '@/utils/accessibility';

/**
 * Invisible ARIA live regions for screen reader announcements.
 * Place once at the app root (e.g., in AppLayout).
 */
export function ScreenReaderAnnouncer() {
    return (
        <>
            <div {...screenReaderAnnouncerProps.polite} />
            <div {...screenReaderAnnouncerProps.assertive} />
        </>
    );
}

/**
 * Skip to main content link — visible only on keyboard focus.
 * First focusable element in the DOM for keyboard users.
 */
export function SkipToContent({ targetId = 'main-content' }: { targetId?: string }) {
    return (
        <a
            href={`#${targetId}`}
            className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[9999] focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-white focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2"
            onClick={(e) => {
                e.preventDefault();
                const target = document.getElementById(targetId);
                if (target) {
                    target.setAttribute('tabindex', '-1');
                    target.focus();
                    target.removeAttribute('tabindex');
                }
            }}
        >
            Skip to main content
        </a>
    );
}

/**
 * Visually hidden text for screen readers only.
 */
export function VisuallyHidden({ children }: { children: React.ReactNode }) {
    return <span className="sr-only">{children}</span>;
}

/**
 * Accessible icon button with required aria-label.
 */
export function IconButton({
    label,
    onClick,
    children,
    className = '',
    disabled = false,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
    className?: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            onClick={onClick}
            disabled={disabled}
            className={`inline-flex items-center justify-center rounded-lg p-2 min-w-[44px] min-h-[44px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
        >
            {children}
        </button>
    );
}

/**
 * Accessible loading spinner with announcement.
 */
export function LoadingSpinner({ label = 'Loading...' }: { label?: string }) {
    return (
        <div role="status" aria-label={label} className="flex items-center justify-center p-4">
            <svg
                className="animate-spin h-6 w-6 text-indigo-600 dark:text-indigo-400"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span className="sr-only">{label}</span>
        </div>
    );
}

/**
 * Accessible empty state with icon and message.
 */
export function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon?: React.ReactNode;
    title: string;
    description?: string;
    action?: React.ReactNode;
}) {
    return (
        <div role="status" className="flex flex-col items-center justify-center py-12 px-4 text-center">
            {icon && <div className="mb-4 text-gray-400 dark:text-gray-600" aria-hidden="true">{icon}</div>}
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">{title}</h3>
            {description && (
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
