import { useSyncExternalStore } from 'react';
import { isPasskeySupported } from '@/lib/passkeys';

function subscribeToPasskeySupport(): () => void {
    return () => {};
}

function getPasskeySupportSnapshot(): boolean {
    return isPasskeySupported();
}

function getServerSnapshot(): boolean {
    return false;
}

export function usePasskeySupport(): boolean {
    return useSyncExternalStore(
        subscribeToPasskeySupport,
        getPasskeySupportSnapshot,
        getServerSnapshot,
    );
}
