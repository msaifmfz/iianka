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

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

export type FlashToastType = 'success' | 'error' | 'warning' | 'info';

export type FlashResourceType =
    | 'construction_schedule'
    | 'business_schedule'
    | 'internal_notice'
    | 'cleaning_duty_rule'
    | 'site_guide_file'
    | 'admin_user'
    | 'stock'
    | 'stock_purchase_cell'
    | 'stock_term_memo'
    | 'voucher_confirmation'
    | 'attendance_cell'
    | 'construction_subcontractor'
    | 'reception_case'
    | 'reception_document_type';

export type FlashResourceAction =
    | 'created'
    | 'updated'
    | 'saved'
    | 'submitted'
    | 'assigned'
    | 'started'
    | 'handed_over'
    | 'completed';

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
