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

interface Props {
    plan: Plan;
}

export default function PlanShow({ plan }: Props) {
    const [showCompleted, setShowCompleted] = useState(true);
    const progress = plan.duration > 0 ? (plan.daysCompleted / plan.duration) * 100 : 0;

    const visibleReadings = showCompleted
        ? plan.readings
        : plan.readings.filter(r => !r.completed);

    // Find next unread day
    const nextDay = plan.readings.find(r => !r.completed);

    return (
        <AppLayout title={plan.title}>
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                {/* Header */}
                <div className="mb-6">
                    <Link href="/plans" className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline mb-2 inline-block">
                        ← Back to Plans
                    </Link>
                    <div className="flex items-start justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{plan.title}</h1>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{plan.description}</p>
                        </div>
                        <span className="text-xs font-medium px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                            {plan.category}
                        </span>
                    </div>
                </div>

                {/* Progress overview */}
                <div className="mb-8 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm">
                    <div className="grid grid-cols-3 gap-4 text-center mb-4">
                        <div>
                            <div className="text-2xl font-bold text-gray-900 dark:text-white">{plan.daysCompleted}</div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Days Done</div>
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-gray-900 dark:text-white">{plan.duration - plan.daysCompleted}</div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Days Left</div>
                        </div>
                        <div>
                            <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{Math.round(progress)}%</div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Complete</div>
                        </div>
                    </div>
                    <div className="h-3 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div
                            className="h-full rounded-full bg-indigo-500 transition-all duration-500"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                    {plan.startDate && (
                        <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                            Started {plan.startDate}
                        </p>
                    )}
                </div>

                {/* Next reading highlight */}
                {nextDay && (
                    <div className="mb-6 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 p-4">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">📖</span>
                            <div>
                                <div className="text-sm font-semibold text-indigo-700 dark:text-indigo-300">
                                    Today&apos;s Reading — Day {nextDay.day}
                                </div>
                                <div className="text-sm text-indigo-600 dark:text-indigo-400">
                                    {nextDay.passages.join(' · ')}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Controls */}
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Daily Readings</h2>
                    <label className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={showCompleted}
                            onChange={e => setShowCompleted(e.target.checked)}
                            className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                        />
                        Show completed
                    </label>
                </div>

                {/* Reading list */}
                <div className="space-y-2">
                    {visibleReadings.map(reading => (
                        <div
                            key={reading.day}
                            className={`flex items-center gap-4 rounded-xl border p-4 transition-all ${
                                reading.completed
                                    ? 'bg-gray-50 dark:bg-gray-900/50 border-gray-100 dark:border-gray-800/50 opacity-60'
                                    : reading.day === nextDay?.day
                                        ? 'bg-white dark:bg-gray-900 border-indigo-300 dark:border-indigo-700 shadow-sm'
                                        : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800'
                            }`}
                        >
                            <div className={`flex-none h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold ${
                                reading.completed
                                    ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                            }`}>
                                {reading.completed ? '✓' : reading.day}
                            </div>

                            <div className="flex-1 min-w-0">
                                <div className="text-sm font-medium text-gray-900 dark:text-white">
                                    Day {reading.day}
                                </div>
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    {reading.passages.join(' · ')}
                                </div>
                            </div>

                            {!reading.completed && (
                                <span className="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                                    Read →
                                </span>
                            )}
                        </div>
                    ))}
                </div>

                {visibleReadings.length === 0 && (
                    <div className="text-center py-12 text-gray-500 dark:text-gray-400">
                        <span className="text-4xl block mb-4">🎉</span>
                        <p className="text-lg font-medium">All readings complete!</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
