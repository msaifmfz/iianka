import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type AttentionSummary = {
    schedule_count: number;
    pending_voucher_count: number;
    internal_notice_count: number;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    badge?: number | string | null;
    isActive?: boolean;
};
