import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { PaginatedResponse } from '@/types/ui';

type Props = {
    paginated: Pick<
        PaginatedResponse<unknown>,
        'from' | 'to' | 'total' | 'links'
    >;
};

export function Pagination({ paginated }: Props) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
            <p>
                {paginated.total > 0
                    ? `${paginated.from} - ${paginated.to} / ${paginated.total} 件`
                    : '0 件'}
            </p>
            <div className="flex flex-wrap gap-2">
                {paginated.links.map((link, index) => (
                    <Button
                        key={`${link.label}-${index}`}
                        asChild={link.url !== null}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        disabled={link.url === null}
                    >
                        {link.url ? (
                            <Link href={link.url} preserveScroll>
                                {link.label}
                            </Link>
                        ) : (
                            <span>{link.label}</span>
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}
