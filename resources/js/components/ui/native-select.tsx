import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A plain, styled `<select>` — the reception forms use native selects (rather
 * than the Radix `Select`) for lighter, mobile-friendly pickers. Owns the shared
 * class string so it is defined once. Size overrides can be passed via className.
 */
export function NativeSelect({ className, ...props }: ComponentProps<'select'>) {
    return (
        <select
            data-slot="native-select"
            className={cn(
                'border-input bg-background flex h-9 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
