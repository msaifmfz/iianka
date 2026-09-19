import { CircleHelp } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

function subscribeHover(callback: () => void) {
    const media = window.matchMedia('(hover: hover) and (pointer: fine)');
    media.addEventListener('change', callback);

    return () => media.removeEventListener('change', callback);
}

function supportsHover(): boolean {
    return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

export function ActionHelp({
    title,
    children,
    embedded = false,
}: {
    title: string;
    children: string;
    embedded?: boolean;
}) {
    const isMobile = useIsMobile();
    const hasHover = useSyncExternalStore(
        subscribeHover,
        supportsHover,
        () => false,
    );
    const trigger = (
        <Button
            type="button"
            size="icon"
            variant="ghost"
            className={cn(
                'shrink-0 rounded-full bg-background/90 text-muted-foreground',
                embedded ? 'size-6' : 'size-8',
            )}
            aria-label={`${title}の使い方`}
        >
            <CircleHelp className="size-4" />
        </Button>
    );

    if (!isMobile && hasHover) {
        return (
            <TooltipProvider delayDuration={200}>
                <Tooltip>
                    <TooltipTrigger asChild>{trigger}</TooltipTrigger>
                    <TooltipContent
                        className="z-[2000] max-w-72 text-sm leading-6"
                        side="bottom"
                    >
                        <p className="font-semibold">{title}</p>
                        <p>{children}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription className="pt-2 text-left leading-7">
                        {children}
                    </DialogDescription>
                </DialogHeader>
                <DialogClose asChild>
                    <Button type="button">わかりました</Button>
                </DialogClose>
            </DialogContent>
        </Dialog>
    );
}

export function HelpButton({
    help,
    helpTitle,
    className,
    size,
    ...props
}: ComponentProps<typeof Button> & {
    help: string;
    helpTitle: string;
}) {
    return (
        <span className="relative inline-flex max-w-full items-center">
            <Button
                {...props}
                size={size}
                className={cn(
                    className,
                    'pr-10!',
                    size === 'icon' && 'w-16 pl-3',
                )}
            />
            <span className="absolute inset-y-0 right-1 flex items-center">
                <ActionHelp title={helpTitle} embedded>
                    {help}
                </ActionHelp>
            </span>
        </span>
    );
}
