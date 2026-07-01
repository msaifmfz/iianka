import { usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { FlashToastType } from '@/types/ui';

const icons: Record<FlashToastType, LucideIcon> = {
    success: CheckCircle2,
    error: AlertCircle,
    warning: AlertTriangle,
    info: Info,
};

const styles: Record<
    FlashToastType,
    {
        accent: string;
        icon: string;
    }
> = {
    success: {
        accent: 'bg-emerald-500',
        icon: 'text-emerald-600 dark:text-emerald-400',
    },
    error: {
        accent: 'bg-red-500',
        icon: 'text-red-600 dark:text-red-400',
    },
    warning: {
        accent: 'bg-amber-500',
        icon: 'text-amber-600 dark:text-amber-400',
    },
    info: {
        accent: 'bg-sky-500',
        icon: 'text-sky-600 dark:text-sky-400',
    },
};

export default function FlashNotifications() {
    const { flash } = usePage();
    const [dismissedToastId, setDismissedToastId] = useState<string | null>(
        null,
    );
    const toast =
        flash.toast !== undefined && flash.toast.id !== dismissedToastId
            ? flash.toast
            : null;

    useEffect(() => {
        if (toast === null) {
            return;
        }

        const timeout = window.setTimeout(
            () => setDismissedToastId(toast.id),
            5000,
        );

        return () => window.clearTimeout(timeout);
    }, [toast]);

    if (toast === null) {
        return null;
    }

    const Icon = icons[toast.type];
    const variant = styles[toast.type];
    const isAssertive = toast.type === 'error' || toast.type === 'warning';

    return (
        <div className="pointer-events-none fixed inset-x-0 top-3 z-50 flex px-3 sm:inset-x-auto sm:top-4 sm:right-4 sm:w-[min(24rem,calc(100vw-2rem))] sm:px-0">
            <div
                role={isAssertive ? 'alert' : 'status'}
                aria-live={isAssertive ? 'assertive' : 'polite'}
                className="pointer-events-auto relative w-full overflow-hidden rounded-lg border border-border/80 bg-background text-foreground shadow-lg ring-1 shadow-black/10 ring-black/5 dark:shadow-black/40"
            >
                <div
                    className={cn(
                        'absolute inset-y-0 left-0 w-1',
                        variant.accent,
                    )}
                />
                <div className="flex items-start gap-3 py-3 pr-3 pl-4">
                    <Icon
                        aria-hidden="true"
                        className={cn('mt-0.5 size-5 shrink-0', variant.icon)}
                    />
                    <p className="min-w-0 flex-1 text-sm leading-5 break-words">
                        {toast.message}
                    </p>
                    <button
                        type="button"
                        aria-label="通知を閉じる"
                        className="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        onClick={() => setDismissedToastId(toast.id)}
                    >
                        <X aria-hidden="true" className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}
