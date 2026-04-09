import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/utils';

type FloatingBackButtonProps = {
    onClick: () => void;
    label?: string;
    className?: string;
};

export function FloatingBackButton({
    onClick,
    label = '予定表へ戻る',
    className,
}: FloatingBackButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            className={cn(
                'fixed bottom-5 left-1/2 z-[70] inline-flex size-15 -translate-x-1/2 items-center justify-center rounded-full border border-amber-200/90 bg-amber-500 text-white shadow-2xl shadow-amber-500/35 ring-4 ring-white/85 backdrop-blur transition-all duration-200 hover:-translate-x-1/2 hover:-translate-y-0.5 hover:bg-amber-400 hover:shadow-2xl hover:shadow-amber-500/40 focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none md:bottom-6 xl:bottom-8 dark:border-amber-300/20 dark:bg-amber-500 dark:text-neutral-950 dark:ring-neutral-950/80 dark:hover:bg-amber-400',
                className,
            )}
        >
            <ArrowLeft className="size-5" />
            <span className="sr-only">{label}</span>
        </button>
    );
}
