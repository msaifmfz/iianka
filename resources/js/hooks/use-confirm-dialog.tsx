import { useCallback, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmOptions = {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
};

type ConfirmState = ConfirmOptions & { open: boolean };

/**
 * A promise-based replacement for the native `confirm()`. Render the returned
 * `dialog` once, then `await confirm({ ... })` in an event handler:
 *
 *   const { confirm, dialog } = useConfirmDialog();
 *   if (!(await confirm({ title: '削除しますか？', variant: 'destructive' }))) return;
 */
export function useConfirmDialog() {
    const [state, setState] = useState<ConfirmState | null>(null);
    const resolverRef = useRef<((value: boolean) => void) | null>(null);

    const settle = useCallback((result: boolean) => {
        resolverRef.current?.(result);
        resolverRef.current = null;
        setState((current) =>
            current ? { ...current, open: false } : current,
        );
    }, []);

    const confirm = useCallback((options: ConfirmOptions): Promise<boolean> => {
        // Resolve any prior pending confirm to false before opening a new one.
        resolverRef.current?.(false);

        return new Promise<boolean>((resolve) => {
            resolverRef.current = resolve;
            setState({ ...options, open: true });
        });
    }, []);

    const dialog = (
        <Dialog
            open={state?.open ?? false}
            onOpenChange={(open) => {
                if (!open) {
                    settle(false);
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{state?.title}</DialogTitle>
                    {state?.description ? (
                        <DialogDescription>
                            {state.description}
                        </DialogDescription>
                    ) : null}
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => settle(false)}
                    >
                        {state?.cancelLabel ?? 'キャンセル'}
                    </Button>
                    <Button
                        type="button"
                        variant={state?.variant ?? 'default'}
                        onClick={() => settle(true)}
                    >
                        {state?.confirmLabel ?? 'OK'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return { confirm, dialog };
}
