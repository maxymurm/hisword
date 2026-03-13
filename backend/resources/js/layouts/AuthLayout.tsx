import { PropsWithChildren } from 'react';
import { Head, Link } from '@inertiajs/react';

interface Props {
    title: string;
    description?: string;
}

export default function AuthLayout({ title, description, children }: PropsWithChildren<Props>) {
    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 dark:bg-gray-950 px-4 py-12 sm:px-6 lg:px-8">
                {/* Logo */}
                <Link href="/" className="mb-8 flex items-center gap-2">
                    <span className="text-3xl">📖</span>
                    <span className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                        HisWord
                    </span>
                </Link>

                {/* Card */}
                <div className="w-full max-w-md">
                    <div className="rounded-2xl bg-white dark:bg-gray-900 px-6 py-8 shadow-xl ring-1 ring-gray-200 dark:ring-gray-800 sm:px-10">
                        <div className="mb-6 text-center">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {description}
                                </p>
                            )}
                        </div>

                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}
