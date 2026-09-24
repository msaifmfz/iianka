import { cn } from '@/lib/utils';
import type { ClientSummary } from '@/types';

/**
 * A neutral short label for recognizing clients in lists and detail pages.
 */
export default function ClientBadge({
    client,
    className,
}: {
    client: Pick<ClientSummary, 'short_label'>;
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold text-muted-foreground shadow-sm ring-2 ring-white dark:ring-neutral-900',
                className,
            )}
        >
            {client.short_label}
        </span>
    );
}
