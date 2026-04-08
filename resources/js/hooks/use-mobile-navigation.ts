import { useCallback } from 'react';
import { useSidebar } from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
    const { setOpenMobile } = useSidebar();
    const isMobile = useIsMobile();

    return useCallback(() => {
        // Remove pointer-events style from body...
        document.body.style.removeProperty('pointer-events');

        if (isMobile) {
            setOpenMobile(false);
        }
    }, [isMobile, setOpenMobile]);
}
