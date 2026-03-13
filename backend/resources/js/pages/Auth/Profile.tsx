import { FormEvent, useRef, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';
import Card, { CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import Modal from '@/components/ui/Modal';
import type { PageProps } from '@/types';

export default function Profile() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user!;

    return (
        <AppLayout title="Profile Settings">
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Profile Settings
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your account information and security
                    </p>
                </div>

                <ProfileInfoSection user={user} />
                <PasswordSection />
                <DangerSection />
            </div>
        </AppLayout>
    );
}

// ── Profile Information ─────────────────────────────────────────

function ProfileInfoSection({ user }: { user: { name: string; email: string; email_verified_at?: string } }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/profile');
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Profile Information</CardTitle>
                <CardDescription>
                    Update your name and email address
                </CardDescription>
            </CardHeader>

            <form onSubmit={submit} className="p-6 pt-0 space-y-4">
                {/* Avatar placeholder */}
                <div className="flex items-center gap-4">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                        {user.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-900 dark:text-white">
                            {user.name}
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {user.email}
                            {user.email_verified_at && (
                                <span className="ml-1 text-green-600 dark:text-green-400">✓ Verified</span>
                            )}
                        </p>
                    </div>
                </div>

                <Input
                    label="Name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    autoComplete="name"
                    required
                />

                <Input
                    label="Email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    autoComplete="email"
                    required
                />

                <div className="flex items-center gap-3">
                    <Button type="submit" loading={processing}>
                        Save changes
                    </Button>

                    {recentlySuccessful && (
                        <span className="text-sm text-green-600 dark:text-green-400">
                            Saved!
                        </span>
                    )}
                </div>
            </form>
        </Card>
    );
}

// ── Password ────────────────────────────────────────────────────

function PasswordSection() {
    const passwordRef = useRef<HTMLInputElement>(null);
    const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                }
                if (errors.current_password) {
                    reset('current_password');
                    passwordRef.current?.focus();
                }
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Update Password</CardTitle>
                <CardDescription>
                    Ensure your account uses a long, random password
                </CardDescription>
            </CardHeader>

            <form onSubmit={submit} className="p-6 pt-0 space-y-4">
                <Input
                    ref={passwordRef}
                    label="Current password"
                    type="password"
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    error={errors.current_password}
                    autoComplete="current-password"
                    required
                />

                <Input
                    label="New password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    autoComplete="new-password"
                    hint="Must be at least 8 characters"
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

                <div className="flex items-center gap-3">
                    <Button type="submit" loading={processing}>
                        Update password
                    </Button>

                    {recentlySuccessful && (
                        <span className="text-sm text-green-600 dark:text-green-400">
                            Updated!
                        </span>
                    )}
                </div>
            </form>
        </Card>
    );
}

// ── Danger Zone ─────────────────────────────────────────────────

function DangerSection() {
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({
        password: '',
    });

    const confirmDelete = (e: FormEvent) => {
        e.preventDefault();
        destroy('/profile', {
            preserveScroll: true,
            onSuccess: () => setShowDeleteModal(false),
            onError: () => reset(),
            onFinish: () => reset(),
        });
    };

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle className="text-red-600 dark:text-red-400">
                        Danger Zone
                    </CardTitle>
                    <CardDescription>
                        Permanently delete your account and all associated data
                    </CardDescription>
                </CardHeader>

                <div className="p-6 pt-0">
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Once your account is deleted, all of its resources, bookmarks, notes,
                        highlights, and reading progress will be permanently removed.
                    </p>

                    <Button
                        variant="danger"
                        onClick={() => setShowDeleteModal(true)}
                    >
                        Delete account
                    </Button>
                </div>
            </Card>

            <Modal
                open={showDeleteModal}
                onClose={() => {
                    setShowDeleteModal(false);
                    reset();
                }}
                title="Delete Account"
            >
                <form onSubmit={confirmDelete} className="space-y-4">
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Are you sure you want to delete your account? This action cannot be undone.
                        Enter your password to confirm.
                    </p>

                    <Input
                        label="Password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        autoFocus
                        required
                    />

                    <div className="flex justify-end gap-3">
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setShowDeleteModal(false);
                                reset();
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            loading={processing}
                        >
                            Delete my account
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
