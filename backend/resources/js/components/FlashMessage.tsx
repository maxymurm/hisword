import { useEffect, useState } from 'react';
import type { FlashMessages } from '@/types';

interface Props {
    flash: FlashMessages;
}

export default function FlashMessage({ flash }: Props) {
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState<{ type: string; text: string } | null>(null);

    useEffect(() => {
        if (flash.success) {
            setMessage({ type: 'success', text: flash.success });
            setVisible(true);
        } else if (flash.error) {
            setMessage({ type: 'error', text: flash.error });
            setVisible(true);
        } else if (flash.info) {
            setMessage({ type: 'info', text: flash.info });
            setVisible(true);
        }
    }, [flash]);

    useEffect(() => {
        if (visible) {
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [visible]);

    if (!visible || !message) return null;

    const colors = {
        success: 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200',
        error: 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
        info: 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
    };

    return (
        <div className="fixed top-16 right-4 z-50 animate-slide-in">
            <div className={`rounded-lg border px-4 py-3 shadow-lg ${colors[message.type as keyof typeof colors]}`}>
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{message.text}</span>
                    <button
                        onClick={() => setVisible(false)}
                        className="ml-2 opacity-60 hover:opacity-100"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </div>
    );
}
