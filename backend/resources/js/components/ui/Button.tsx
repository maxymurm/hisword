import { ButtonHTMLAttributes, PropsWithChildren } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';
type Size = 'sm' | 'md' | 'lg';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
}

const variants: Record<Variant, string> = {
    primary:
        'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 shadow-sm',
    secondary:
        'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
    danger:
        'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600 shadow-sm',
    ghost:
        'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100',
};

const sizes: Record<Size, string> = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3 py-2 text-sm',
    lg: 'px-4 py-2.5 text-base',
};

export default function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    disabled,
    children,
    className = '',
    ...props
}: PropsWithChildren<Props>) {
    return (
        <button
            {...props}
            disabled={disabled || loading}
            className={`
                inline-flex items-center justify-center gap-2 rounded-lg font-semibold
                transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed
                ${variants[variant]} ${sizes[size]} ${className}
            `}
        >
            {loading && (
                <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            )}
            {children}
        </button>
    );
}
