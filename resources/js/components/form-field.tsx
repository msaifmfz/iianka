import type { ReactNode } from 'react';
import { RequiredBadge } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function FormField({
    label,
    required = false,
    error,
    children,
    className,
}: {
    label: ReactNode;
    required?: boolean;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <label className={cn('grid gap-2 text-sm font-medium', className)}>
            <span className="flex items-center gap-2">
                <span>{label}</span>
                {required && <RequiredBadge />}
            </span>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </label>
    );
}
