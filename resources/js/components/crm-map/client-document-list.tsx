import { FileImage, FileSpreadsheet, FileText, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCrmDate } from '@/lib/crm';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ClientDocument } from '@/types';

const spreadsheetExtensions = ['xls', 'xlsx', 'csv'];
const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

/**
 * The icon for a stored file, picked from its extension so a spreadsheet
 * reads differently from a scan at a glance.
 */
export function DocumentIcon({
    extension,
    className,
}: {
    extension: string | null;
    className?: string;
}) {
    const normalized = extension?.toLowerCase() ?? '';
    const Icon = spreadsheetExtensions.includes(normalized)
        ? FileSpreadsheet
        : imageExtensions.includes(normalized)
          ? FileImage
          : FileText;

    return <Icon className={cn('size-4 shrink-0', className)} />;
}

/**
 * A link that opens PDFs and images in a new tab and downloads everything
 * else, matching how the server serves them.
 */
export function DocumentLink({
    href,
    name,
    extension,
    opensInline,
    className,
}: {
    href: string;
    name: string;
    extension: string | null;
    opensInline: boolean;
    className?: string;
}) {
    const fileName = extension ? `${name}.${extension}` : name;

    return (
        <a
            href={href}
            {...(opensInline
                ? { target: '_blank', rel: 'noreferrer' }
                : { download: fileName })}
            className={cn(
                'flex min-w-0 items-center gap-2 font-medium hover:underline',
                className,
            )}
        >
            <DocumentIcon
                extension={extension}
                className="text-muted-foreground"
            />
            <span className="truncate">{name}</span>
            {extension && (
                <span className="shrink-0 text-xs font-normal text-muted-foreground uppercase">
                    {extension}
                </span>
            )}
        </a>
    );
}

/**
 * Documents filed on a client: newest first, each with where it belongs and
 * who filed it.
 */
export default function ClientDocumentList({
    documents,
    currentPlaceId,
    onDelete,
}: {
    documents: ClientDocument[];
    /** Marks documents filed on this place when shown inside its panel. */
    currentPlaceId?: number;
    onDelete: (document: ClientDocument) => void;
}) {
    return (
        <ul className="divide-y rounded-xl border bg-white text-sm dark:border-neutral-800 dark:bg-neutral-950">
            {documents.map((clientDocument) => (
                <li
                    key={clientDocument.id}
                    className="flex items-start gap-2 px-3 py-2.5"
                >
                    <div className="min-w-0 flex-1 space-y-0.5">
                        <DocumentLink
                            href={clientDocument.url}
                            name={clientDocument.name}
                            extension={clientDocument.extension}
                            opensInline={clientDocument.opens_inline}
                        />
                        <p className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                            <span>
                                {clientDocument.issued_on
                                    ? formatCrmDate(clientDocument.issued_on)
                                    : `登録 ${formatCrmDate(clientDocument.created_at)}`}
                            </span>
                            <span>
                                {clientDocument.place
                                    ? clientDocument.place.id === currentPlaceId
                                        ? 'この地点'
                                        : clientDocument.place.name
                                    : '顧客全体'}
                            </span>
                            {clientDocument.uploader && (
                                <span>{clientDocument.uploader.name}</span>
                            )}
                            <span>{formatBytes(clientDocument.size)}</span>
                        </p>
                    </div>
                    {clientDocument.can_delete && (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7 shrink-0"
                            aria-label={`${clientDocument.name}を削除`}
                            onClick={() => onDelete(clientDocument)}
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    )}
                </li>
            ))}
        </ul>
    );
}
