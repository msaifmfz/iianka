import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function toggleNumber(values: number[], value: number) {
    return values.includes(value)
        ? values.filter((item) => item !== value)
        : [...values, value];
}

export function phoneHref(phone: string) {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}
