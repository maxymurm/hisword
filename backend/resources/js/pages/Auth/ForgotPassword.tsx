import { FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';

interface Props {
    status?: string;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout
            title="Forgot password?"
            description="Enter your email and we'll send you a reset link"
        >
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

                <Button type="submit" className="w-full" loading={processing}>
                    Send reset link
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Remember your password?{' '}
                <Link
                    href="/login"
                    className="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                >
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
