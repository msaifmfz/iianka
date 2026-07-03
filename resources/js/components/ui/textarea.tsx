import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A styled `<textarea>` owning the shared class string used across the reception
 * forms. Height overrides (e.g. `min-h-36`) can be passed via className.
 */
export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
