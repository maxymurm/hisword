import { useState } from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Reading {
    day: number;
    passages: string[];
    completed: boolean;
}

interface Plan {
    id: number;
    slug: string;
    title: string;
    description: string;
    duration: number;
    category: string;
    daysCompleted: number;
    isActive: boolean;
    startDate: string | null;
    readings: Reading[];
}

interface Streak {
    current: number;
    longest: number;
    totalDays: number;
    thisWeek: boolean[];
}

interface Props {
    plans: Plan[];
    streak: Streak;
}

export default function PlansIndex({ plans, streak }: Props) {
    const [filter, setFilter] = useState<'all' | 'active' | 'completed'>('all');

    const filtered = plans.filter(p => {
        if (filter === 'active') return p.isActive;
        if (filter === 'completed') return p.daysCompleted >= p.duration;
        return true;
    });

    return (
        <AppLayout title="Reading Plans">
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Reading Plans</h1>
                    <span className="text-sm text-gray-500 dark:text-gray-400">{plans.length} plans</span>
                </div>

                {/* Streak Card */}
                <div className="mb-8 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white shadow-lg">
                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-3xl">🔥</span>
                                <span className="text-3xl font-bold">{streak.current}</span>
                                <span className="text-lg opacity-90">day streak</span>
                            </div>
                            <p className="mt-1 text-sm opacity-80">
                                Longest: {streak.longest} days · Total: {streak.totalDays} days read
                            </p>
                        </div>
                        <div className="flex gap-1.5">
                            {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((day, i) => (
                                <div key={i} className="flex flex-col items-center gap-1">
                                    <span className="text-xs opacity-70">{day}</span>
                                    <div className={`h-8 w-8 rounded-lg flex items-center justify-center text-sm font-medium ${
                                        streak.thisWeek[i]
                                            ? 'bg-white/30'
                                            : 'bg-white/10'
                                    }`}>
                                        {streak.thisWeek[i] ? '✓' : '·'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Filter tabs */}
                <div className="flex gap-2 mb-6">
                    {(['all', 'active', 'completed'] as const).map(f => (
                        <button
                            key={f}
                            onClick={() => setFilter(f)}
                            className={`px-3 py-1.5 text-sm font-medium rounded-lg capitalize transition-colors ${
                                filter === f
                                    ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                    : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'
                            }`}
                        >
                            {f}
                        </button>
                    ))}
                </div>

                {/* Plans grid */}
                <div className="grid gap-4 sm:grid-cols-2">
                    {filtered.map(plan => {
                        const progress = plan.duration > 0 ? (plan.daysCompleted / plan.duration) * 100 : 0;
                        const isComplete = plan.daysCompleted >= plan.duration;
                        return (
                            <Link
                                key={plan.id}
                                href={`/plans/${plan.slug}`}
                                className="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-700 transition-all"
                            >
                                <div className="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            {plan.title}
                                        </h3>
                                        <span className="mt-0.5 inline-block text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                            {plan.category}
                                        </span>
                                    </div>
                                    {plan.isActive && (
                                        <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                            Active
                                        </span>
                                    )}
                                    {isComplete && (
                                        <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">
                                            ✓ Complete
                                        </span>
                                    )}
                                </div>

                                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4 line-clamp-2">
                                    {plan.description}
                                </p>

                                {/* Progress bar */}
                                <div className="space-y-1.5">
                                    <div className="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>{plan.daysCompleted} / {plan.duration} days</span>
                                        <span>{Math.round(progress)}%</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                        <div
                                            className="h-full rounded-full bg-indigo-500 transition-all duration-500"
                                            style={{ width: `${progress}%` }}
                                        />
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>

                {filtered.length === 0 && (
                    <div className="text-center py-16 text-gray-500 dark:text-gray-400">
                        <span className="text-4xl block mb-4">📅</span>
                        <p className="text-lg font-medium">No plans found</p>
                        <p className="text-sm mt-1">Try a different filter</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
