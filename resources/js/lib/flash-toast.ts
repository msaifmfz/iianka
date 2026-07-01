import { router } from '@inertiajs/react';
import type { FlashResource, FlashToast, FlashToastType } from '@/types/ui';

function toastId(): string {
    return (
        globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`
    );
}

export function flashToast(
    message: string,
    type: FlashToastType = 'success',
    resource?: FlashResource,
): void {
    router.flash('toast', {
        id: toastId(),
        type,
        message,
        ...(resource === undefined ? {} : { resource }),
    } satisfies FlashToast);
}
