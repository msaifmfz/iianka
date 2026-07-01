import { CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { FlashResourceAction } from '@/types/ui';

export const recentResourceHighlightClass =
    'border-emerald-300 bg-emerald-50/70 ring-2 ring-emerald-500/70 ring-offset-2 ring-offset-background dark:border-emerald-700 dark:bg-emerald-950/30 dark:ring-emerald-400/70';

const actionLabels: Record<FlashResourceAction, string> = {
    created: '追加済み',
    updated: '更新済み',
    saved: '保存済み',
};

export function recentResourceActionLabel(action: FlashResourceAction): string {
    return actionLabels[action];
}

export function RecentResourceBadge({
    action,
    className,
}: {
    action: FlashResourceAction;
    className?: string;
}) {
    return (
        <span
            role="status"
            aria-live="polite"
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-800',
                className,
            )}
        >
            <CheckCircle2 aria-hidden="true" className="size-3.5" />
            {recentResourceActionLabel(action)}
        </span>
    );
}
