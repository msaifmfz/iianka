import { clientLabelColor } from '@/lib/crm-colors';
import { cn } from '@/lib/utils';
import type { ClientSummary } from '@/types';

/**
 * The client's color and short label, drawn the same way as its map pins so
 * lists and the legend read as the same client at a glance.
 */
export default function ClientBadge({
    client,
    className,
}: {
    client: Pick<ClientSummary, 'color' | 'short_label'>;
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm ring-2 ring-white dark:ring-neutral-900',
                className,
            )}
            style={{
                backgroundColor: client.color,
                color: clientLabelColor(client.color),
            }}
        >
            {client.short_label}
        </span>
    );
}
