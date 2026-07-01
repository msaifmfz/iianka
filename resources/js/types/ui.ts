import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

export type FlashToastType = 'success' | 'error' | 'warning' | 'info';

export type FlashResourceType =
    | 'construction_schedule'
    | 'business_schedule'
    | 'internal_notice'
    | 'cleaning_duty_rule'
    | 'site_guide_file'
    | 'admin_user'
    | 'voucher_confirmation'
    | 'attendance_cell'
    | 'construction_subcontractor';

export type FlashResourceAction = 'created' | 'updated' | 'saved';

export type FlashResource = {
    type: FlashResourceType;
    id: number | string;
    action: FlashResourceAction;
    label: string;
};

export type FlashToast = {
    id: string;
    type: FlashToastType;
    message: string;
    resource?: FlashResource;
};
