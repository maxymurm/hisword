import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';

interface Props {
    email: string;
    token: string;
}

export default function ResetPassword({ email, token }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Reset password" description="Choose a new password for your account">
            <form onSubmit={submit} className="space-y-4">
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
                    label="New password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    autoComplete="new-password"
                    autoFocus
                    required
                />

                <Input
                    label="Confirm new password"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    autoComplete="new-password"
                    required
                />

                <Button type="submit" className="w-full" loading={processing}>
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
