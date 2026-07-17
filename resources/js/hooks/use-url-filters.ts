import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { QueryParams } from '@/wayfinder';

const defaultDelay = 400;

/**
 * Debounced local filter state mirrored to the URL. Local edits re-visit
 * `url` with the non-empty filters as query params (replace + preserveState),
 * skipping the visit while local state already matches the server-provided
 * filters — so back/forward navigation does not re-trigger a search.
 */
export function useUrlFilters<T extends Record<string, string>>(
    serverFilters: T,
    url: string,
    { delay = defaultDelay }: { delay?: number } = {},
) {
    const [filters, setFilters] = useState<T>(serverFilters);

    useEffect(() => {
        const matchesServer = Object.keys(filters).every(
            (key) => filters[key] === serverFilters[key],
        );

        if (matchesServer) {
            return;
        }

        const timeout = window.setTimeout(() => {
            const compact: QueryParams = Object.fromEntries(
                Object.entries(filters).filter(([, value]) => value !== ''),
            );

            router.get(url, compact, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        }, delay);

        return () => {
            window.clearTimeout(timeout);
        };
    }, [filters, serverFilters, url, delay]);

    function setFilter<K extends keyof T>(key: K, value: T[K]) {
        setFilters((current) => ({ ...current, [key]: value }));
    }

    return { filters, setFilter };
}
