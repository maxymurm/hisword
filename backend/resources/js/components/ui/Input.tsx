import { InputHTMLAttributes, forwardRef } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    hint?: string;
}

const Input = forwardRef<HTMLInputElement, Props>(
    ({ label, error, hint, className = '', id, ...props }, ref) => {
        const inputId = id || label?.toLowerCase().replace(/\s+/g, '-');

        return (
            <div className="w-full">
                {label && (
                    <label
                        htmlFor={inputId}
                        className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={`
                        block w-full rounded-lg border px-3 py-2 text-sm
                        transition-colors duration-150
                        bg-white dark:bg-gray-800
                        text-gray-900 dark:text-gray-100
                        placeholder:text-gray-400 dark:placeholder:text-gray-500
                        focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                        ${error
                            ? 'border-red-300 dark:border-red-600 focus:ring-red-500'
                            : 'border-gray-300 dark:border-gray-600'
                        }
                        ${className}
                    `}
                    {...props}
                />
                {error && (
                    <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>
                )}
                {hint && !error && (
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</p>
                )}
            </div>
        );
    },
);

Input.displayName = 'Input';
export default Input;
