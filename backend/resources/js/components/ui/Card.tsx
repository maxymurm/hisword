import { PropsWithChildren } from 'react';

interface Props {
    className?: string;
    padding?: boolean;
}

export default function Card({ children, className = '', padding = true }: PropsWithChildren<Props>) {
    return (
        <div
            className={`
                rounded-xl bg-white dark:bg-gray-900
                border border-gray-200 dark:border-gray-800
                shadow-sm
                ${padding ? 'p-6' : ''}
                ${className}
            `}
        >
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '' }: PropsWithChildren<{ className?: string }>) {
    return (
        <div className={`mb-4 ${className}`}>
            {children}
        </div>
    );
}

export function CardTitle({ children, className = '' }: PropsWithChildren<{ className?: string }>) {
    return (
        <h3 className={`text-lg font-semibold text-gray-900 dark:text-gray-100 ${className}`}>
            {children}
        </h3>
    );
}

export function CardDescription({ children, className = '' }: PropsWithChildren<{ className?: string }>) {
    return (
        <p className={`text-sm text-gray-500 dark:text-gray-400 mt-1 ${className}`}>
            {children}
        </p>
    );
}
