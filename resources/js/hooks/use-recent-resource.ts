import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FlashResource, FlashResourceType } from '@/types/ui';

const recentResourceDuration = 5000;

export function useRecentResource(): FlashResource | null {
    const { flash } = usePage();
    const toast = flash.toast;
    const resource = toast?.resource;
    const toastId = toast?.id ?? null;
    const resourceKey =
        resource === undefined || toastId === null
            ? null
            : `${toastId}:${resource.type}:${resource.id}:${resource.action}:${resource.label}`;
    const [expiredToastId, setExpiredToastId] = useState<string | null>(null);

    useEffect(() => {
        if (toastId === null || resourceKey === null) {
            return;
        }

        const timeout = window.setTimeout(
            () => setExpiredToastId(toastId),
            recentResourceDuration,
        );

        return () => window.clearTimeout(timeout);
    }, [toastId, resourceKey]);

    if (
        toastId === null ||
        resource === undefined ||
        expiredToastId === toastId
    ) {
        return null;
    }

    return resource;
}

export function recentResourceMatches(
    resource: FlashResource | null,
    type: FlashResourceType,
    id: number | string | null | undefined,
): boolean {
    return (
        resource !== null &&
        id !== null &&
        id !== undefined &&
        resource.type === type &&
        String(resource.id) === String(id)
    );
}
