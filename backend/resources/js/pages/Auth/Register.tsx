import { FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Create your account" description="Start your Bible study journey">
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Full name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    autoComplete="name"
                    autoFocus
                    required
                />

                <Input
                    label="Email address"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    autoComplete="email"
                    required
                />

                <Input
                    label="Password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    autoComplete="new-password"
                    hint="Must be at least 8 characters"
                    required
                />

                <Input
                    label="Confirm password"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    autoComplete="new-password"
                    required
                />

                <Button type="submit" className="w-full" loading={processing}>
                    Create account
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Already have an account?{' '}
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
