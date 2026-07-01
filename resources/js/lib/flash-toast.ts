import { router } from '@inertiajs/react';
import type { FlashToast, FlashToastType } from '@/types/ui';

function toastId(): string {
    return (
        globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`
    );
}

export function flashToast(
    message: string,
    type: FlashToastType = 'success',
): void {
    router.flash('toast', {
        id: toastId(),
        type,
        message,
    } satisfies FlashToast);
}
