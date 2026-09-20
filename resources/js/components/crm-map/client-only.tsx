import type { ReactNode } from 'react';
import { Suspense, useSyncExternalStore } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

const subscribeNever = () => () => {};

/**
 * Leaflet touches `window` as soon as it is imported, and pages are
 * server-rendered, so map modules are lazy-loaded and only rendered in the
 * browser. The server snapshot is `false`, so SSR and the first hydration
 * pass both render the skeleton and agree.
 */
export default function ClientOnly({ children }: { children: ReactNode }) {
    const isBrowser = useSyncExternalStore(
        subscribeNever,
        () => true,
        () => false,
    );

    const fallback = <Skeleton className="size-full rounded-none" />;

    return isBrowser ? (
        <Suspense fallback={fallback}>{children}</Suspense>
    ) : (
        fallback
    );
}
