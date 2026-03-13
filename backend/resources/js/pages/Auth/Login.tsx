import { FormEvent, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';

interface Props {
    status?: string;
}

export default function Login({ status }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Welcome back" description="Sign in to your account">
            {status && (
                <div className="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-600 dark:text-green-400">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Email address"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    autoComplete="email"
                    autoFocus
                    required
                />

                <Input
                    label="Password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    autoComplete="current-password"
                    required
                />

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-800"
                        />
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                            Remember me
                        </span>
                    </label>

                    <Link
                        href="/forgot-password"
                        className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                    >
                        Forgot password?
                    </Link>
                </div>

                <Button type="submit" className="w-full" loading={processing}>
                    Sign in
                </Button>

                {/* Social login placeholders */}
                <div className="relative my-6">
                    <div className="absolute inset-0 flex items-center">
                        <div className="w-full border-t border-gray-200 dark:border-gray-700" />
                    </div>
                    <div className="relative flex justify-center text-sm">
                        <span className="bg-white dark:bg-gray-900 px-4 text-gray-500 dark:text-gray-400">
                            Or continue with
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        disabled
                        className="flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 opacity-50 cursor-not-allowed"
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        Google
                    </button>
                    <button
                        type="button"
                        disabled
                        className="flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 opacity-50 cursor-not-allowed"
                    >
                        <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.18 0-.36-.02-.53-.06-.01-.18-.04-.56-.04-.95 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 17.69c-.413.96-1.614 2.5-2.926 2.52-1.1.02-1.455-.66-2.715-.66-1.26 0-1.66.65-2.694.68-1.263.04-2.464-1.6-2.885-2.56-2.14-4.94-.18-8.91 1.79-10.69 1.08-.98 2.394-1.52 3.634-1.52 1.36 0 2.18.66 3.29.66 1.07 0 1.72-.66 3.26-.66.59 0 2.22.22 3.37 1.78-.1.06-2.01 1.17-1.99 3.5.03 2.79 2.45 3.72 2.47 3.73-.03.06-.38 1.33-1.27 2.63z" />
                        </svg>
                        Apple
                    </button>
                </div>
            </form>

            <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Don&apos;t have an account?{' '}
                <Link
                    href="/register"
                    className="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                >
                    Create one
                </Link>
            </p>
        </AuthLayout>
    );
}
