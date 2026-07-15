import type { ReactNode } from 'react';
import { RequiredBadge } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function FormField({
    label,
    required = false,
    error,
    children,
    className,
    as: Component = 'label',
    labelId,
}: {
    label: ReactNode;
    required?: boolean;
    error?: string;
    children: ReactNode;
    className?: string;
    /**
     * Render a div instead of a label for fields whose control is not
     * labelable (e.g. contenteditable editors); pair with labelId and
     * aria-labelledby on the control.
     */
    as?: 'label' | 'div';
    labelId?: string;
}) {
    return (
        <Component className={cn('grid gap-2 text-sm font-medium', className)}>
            <span id={labelId} className="flex items-center gap-2">
                <span>{label}</span>
                {required && <RequiredBadge />}
            </span>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </Component>
    );
}
