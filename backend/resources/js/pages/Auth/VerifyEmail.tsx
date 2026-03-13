import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/AuthLayout';
import Button from '@/components/ui/Button';

interface Props {
    status?: string;
}

export default function VerifyEmail({ status }: Props) {
    const { post, processing } = useForm({});

    const resend = () => {
        post('/email/verification-notification');
    };

    return (
        <AuthLayout
            title="Verify your email"
            description="Check your inbox for a verification link"
        >
            <div className="space-y-4 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                    <svg className="h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                </div>

                <p className="text-sm text-gray-600 dark:text-gray-400">
                    We&apos;ve sent a verification link to your email address.
                    Please click the link to verify your account.
                </p>

                {status === 'verification-link-sent' && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-600 dark:text-green-400">
                        A new verification link has been sent to your email.
                    </div>
                )}

                <div className="flex flex-col gap-3">
                    <Button
                        onClick={resend}
                        loading={processing}
                        className="w-full"
                    >
                        Resend verification email
                    </Button>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                    >
                        Sign out
                    </Link>
                </div>
            </div>
        </AuthLayout>
    );
}
