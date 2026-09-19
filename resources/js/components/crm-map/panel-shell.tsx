import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { HelpButton as Button } from '@/components/crm-map/action-help';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

/** How far a drag on the handle must travel before it counts as a swipe. */
const SWIPE_THRESHOLD_PX = 40;

/**
 * Holds the history panel over the map without hiding it: a floating card on
 * the right on desktop, a bottom sheet on phones. The sheet opens at half
 * height (map still visible above) and swipes up to full height, or down to
 * collapse and then close.
 */
export default function PanelShell({
    title,
    onClose,
    children,
    expand = false,
}: {
    title: ReactNode;
    onClose: () => void;
    children: ReactNode;
    /** Open the mobile sheet full height, e.g. while a form is being filled in. */
    expand?: boolean;
}) {
    const isMobile = useIsMobile();
    const [isExpanded, setIsExpanded] = useState(expand);
    const dragStartY = useRef<number | null>(null);
    // A swipe also fires a click on release; skip that one so it does not
    // undo the swipe.
    const didSwipe = useRef(false);
    const panelRef = useRef<HTMLElement>(null);
    const wasExpandRequested = useRef(expand);

    useEffect(() => {
        if (expand && !wasExpandRequested.current) {
            setIsExpanded(true);
        }

        wasExpandRequested.current = expand;
    }, [expand]);

    useEffect(() => {
        const previousFocus = document.activeElement;
        panelRef.current?.focus({ preventScroll: true });

        return () => {
            if (
                previousFocus instanceof HTMLElement &&
                previousFocus.isConnected
            ) {
                previousFocus.focus({ preventScroll: true });
            }
        };
    }, [isMobile]);

    useEffect(() => {
        function closeOnEscape(event: KeyboardEvent) {
            if (
                event.key === 'Escape' &&
                !event.defaultPrevented &&
                !document.querySelector('[role="dialog"],[role="alertdialog"]')
            ) {
                onClose();
            }
        }
        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [onClose]);

    if (!isMobile) {
        return (
            <aside
                ref={panelRef}
                tabIndex={-1}
                aria-label="地点の記録"
                className="absolute top-3 right-3 bottom-3 z-[1000] flex w-[26rem] max-w-[calc(100%-1.5rem)] flex-col overflow-hidden rounded-2xl border bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div className="flex items-start gap-2 border-b p-4 dark:border-neutral-800">
                    <div className="min-w-0 flex-1">{title}</div>
                    <Button
                        helpTitle="閉じる"
                        help="地点の履歴を閉じて地図に戻ります。保存していない入力・添付は失われます。"
                        size="icon"
                        variant="ghost"
                        aria-label="閉じる"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </Button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto p-4">
                    {children}
                </div>
            </aside>
        );
    }

    function endDrag(clientY: number) {
        if (dragStartY.current === null) {
            return;
        }

        const distance = clientY - dragStartY.current;
        dragStartY.current = null;
        didSwipe.current = Math.abs(distance) > SWIPE_THRESHOLD_PX;

        if (distance < -SWIPE_THRESHOLD_PX) {
            setIsExpanded(true);
        } else if (distance > SWIPE_THRESHOLD_PX) {
            if (isExpanded) {
                setIsExpanded(false);
            } else {
                onClose();
            }
        }
    }

    return (
        <aside
            ref={panelRef}
            tabIndex={-1}
            aria-label="地点の記録"
            className={cn(
                'absolute inset-x-0 bottom-0 z-[1000] flex flex-col rounded-t-2xl border-t bg-white shadow-[0_-8px_30px_rgb(0_0_0/0.2)] transition-[height] duration-200 motion-reduce:transition-none dark:border-neutral-800 dark:bg-neutral-950',
                isExpanded ? 'h-[92%]' : 'h-[55%]',
            )}
        >
            <button
                type="button"
                aria-label={isExpanded ? '縮小' : '拡大'}
                aria-expanded={isExpanded}
                className="flex min-h-8 w-full touch-none items-center justify-center"
                onClick={() => {
                    if (didSwipe.current) {
                        didSwipe.current = false;

                        return;
                    }

                    setIsExpanded((current) => !current);
                }}
                onPointerDown={(event) => {
                    dragStartY.current = event.clientY;
                    event.currentTarget.setPointerCapture(event.pointerId);
                }}
                onPointerUp={(event) => endDrag(event.clientY)}
                onPointerCancel={() => {
                    dragStartY.current = null;
                }}
            >
                <span className="h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700" />
            </button>
            <div className="flex items-start gap-2 border-b px-4 pb-3 dark:border-neutral-800">
                <div className="min-w-0 flex-1">{title}</div>
                <Button
                    helpTitle="閉じる"
                    help="地点の履歴を閉じて地図に戻ります。上の持ち手を押すと、パネルの拡大・縮小もできます。保存していない入力・添付は失われます。"
                    size="icon"
                    variant="ghost"
                    aria-label="閉じる"
                    onClick={onClose}
                >
                    <X className="size-4" />
                </Button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                {children}
            </div>
        </aside>
    );
}
