import { useEffect } from 'react';

/**
 * Persists window scroll into sessionStorage (keyed by the page's return_to
 * URL) and restores it on mount, so returning from a detail page lands back
 * at the same list position. Restore runs on the first animation frame; saves
 * are rAF-debounced on scroll.
 */
export function useIndexScrollRestore(storagePrefix: string, key: string) {
    useEffect(() => {
        let frameId = 0;
        let pendingFrameId = 0;
        const storageKey = `${storagePrefix}${key}`;

        const restoreScrollPosition = () => {
            const storedScrollPosition =
                window.sessionStorage.getItem(storageKey);

            if (storedScrollPosition === null) {
                return;
            }

            const scrollPosition = Number.parseFloat(storedScrollPosition);

            if (Number.isNaN(scrollPosition)) {
                return;
            }

            window.scrollTo({
                top: scrollPosition,
                behavior: 'auto',
            });
        };

        const saveScrollPosition = () => {
            window.sessionStorage.setItem(storageKey, `${window.scrollY}`);
        };

        const requestScrollSave = () => {
            window.cancelAnimationFrame(pendingFrameId);
            pendingFrameId = window.requestAnimationFrame(saveScrollPosition);
        };

        frameId = window.requestAnimationFrame(restoreScrollPosition);
        requestScrollSave();
        window.addEventListener('scroll', requestScrollSave, {
            passive: true,
        });

        return () => {
            window.cancelAnimationFrame(frameId);
            window.cancelAnimationFrame(pendingFrameId);
            window.removeEventListener('scroll', requestScrollSave);
        };
    }, [storagePrefix, key]);
}
