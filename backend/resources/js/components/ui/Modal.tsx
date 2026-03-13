import { PropsWithChildren, useEffect, useRef } from 'react';

interface Props {
    open: boolean;
    onClose: () => void;
    title?: string;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
}

const maxWidths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
};

export default function Modal({
    open,
    onClose,
    title,
    maxWidth = 'md',
    children,
}: PropsWithChildren<Props>) {
    const dialogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        if (open) {
            document.addEventListener('keydown', handleEscape);
            document.body.style.overflow = 'hidden';
        }
        return () => {
            document.removeEventListener('keydown', handleEscape);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/40 backdrop-blur-sm transition-opacity"
                onClick={onClose}
            />

            {/* Dialog */}
            <div
                ref={dialogRef}
                className={`
                    relative z-10 w-full ${maxWidths[maxWidth]}
                    rounded-2xl bg-white dark:bg-gray-900
                    border border-gray-200 dark:border-gray-800
                    shadow-2xl
                    mx-4
                    animate-modal-in
                `}
            >
                {title && (
                    <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {title}
                        </h2>
                        <button
                            onClick={onClose}
                            className="rounded-lg p-1 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                )}

                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}
