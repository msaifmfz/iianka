import { http, HttpResponseError } from '@inertiajs/core';
import { ExternalLink, FileText, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { previewPdf } from '@/actions/App/Http/Controllers/QuotationController';
import quotationPdf from '@/actions/App/Http/Controllers/QuotationPdfController';
import PdfDocument from '@/components/quotations/pdf-document';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { QuotationInput } from '@/types/quotation';

export function SavedPdfPreviewButton({
    quotationId,
    size,
}: {
    quotationId: number;
    size?: 'sm';
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size={size}>
                    <FileText className="size-4" />
                    PDFを確認
                </Button>
            </DialogTrigger>
            {open && <PdfPreviewContent quotationId={quotationId} />}
        </Dialog>
    );
}

export default function PdfPreview({
    input,
    quotationId,
    onClose,
}: {
    input: QuotationInput;
    quotationId?: number;
    onClose: () => void;
}) {
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <PdfPreviewContent input={input} quotationId={quotationId} />
        </Dialog>
    );
}

function PdfPreviewContent({
    input,
    quotationId,
}:
    | { input: QuotationInput; quotationId?: number }
    | {
          input?: never;
          quotationId: number;
      }) {
    const [document, setDocument] = useState<'quotation' | 'breakdown'>(
        'quotation',
    );
    const [retry, setRetry] = useState(0);
    const [result, setResult] = useState<{
        key: string;
        url?: string;
        error?: string;
    }>();
    const key = `${document}:${retry}`;
    const current = result?.key === key ? result : undefined;
    const label = document === 'quotation' ? '見積書' : '内訳書';
    const url =
        input === undefined
            ? quotationPdf.url(
                  { quotation: quotationId, document },
                  { query: { inline: 1 } },
              )
            : current?.url;

    useEffect(() => {
        if (input === undefined) {
            return;
        }

        const controller = new AbortController();
        let objectUrl: string | undefined;
        const load = async () => {
            try {
                const response = await http.getClient().request({
                    method: 'post',
                    url: previewPdf.url({ document, quotation: quotationId }),
                    data: input,
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (controller.signal.aborted) {
                    return;
                }

                const body = JSON.parse(response.data) as { pdf: string };
                const bytes = Uint8Array.from(atob(body.pdf), (char) =>
                    char.charCodeAt(0),
                );
                objectUrl = URL.createObjectURL(
                    new Blob([bytes], { type: 'application/pdf' }),
                );
                setResult({ key, url: objectUrl });
            } catch (error) {
                if (controller.signal.aborted) {
                    return;
                }

                let message = 'PDFを作成できませんでした。再試行してください。';

                if (
                    error instanceof HttpResponseError &&
                    error.response.status === 422
                ) {
                    const body = JSON.parse(error.response.data) as {
                        errors: Record<string, string[]>;
                    };
                    message = Object.values(body.errors).flat().join(' ');
                }

                setResult({ key, error: message });
            }
        };
        void load();

        return () => {
            controller.abort();

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [input, quotationId, document, key]);

    return (
        <DialogContent className="flex h-[90dvh] max-w-[calc(100%-2rem)] flex-col gap-4 sm:max-w-6xl">
            <DialogHeader>
                <DialogTitle>PDFを確認</DialogTitle>
                <DialogDescription>
                    {input
                        ? '現在の入力内容のプレビューです。見積はまだ発行・保存されません。'
                        : '発行済みの見積書と内訳書を確認できます。'}
                </DialogDescription>
            </DialogHeader>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div
                    className="flex gap-2"
                    role="group"
                    aria-label="プレビューする書類"
                >
                    {(['quotation', 'breakdown'] as const).map((value) => (
                        <Button
                            key={value}
                            type="button"
                            size="sm"
                            variant={document === value ? 'default' : 'outline'}
                            aria-pressed={document === value}
                            onClick={() => setDocument(value)}
                        >
                            {value === 'quotation' ? '見積書' : '内訳書'}
                        </Button>
                    ))}
                </div>
                {url && (
                    <Button asChild variant="ghost" size="sm">
                        <a href={url} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="size-4" />
                            別タブで開く
                        </a>
                    </Button>
                )}
            </div>
            <div className="min-h-0 flex-1 overflow-hidden rounded-lg border bg-muted">
                {url ? (
                    <PdfDocument key={url} url={url} label={label} />
                ) : current?.error ? (
                    <div
                        role="alert"
                        className="flex h-full flex-col items-center justify-center gap-4 p-6 text-center"
                    >
                        <p className="text-sm text-destructive">
                            {current.error}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRetry(retry + 1)}
                        >
                            再試行
                        </Button>
                    </div>
                ) : (
                    <div
                        role="status"
                        className="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground"
                    >
                        <Loader2 className="size-5 animate-spin" />
                        {label}のPDFを作成中…
                    </div>
                )}
            </div>
            {input && (
                <p className="text-xs text-muted-foreground">
                    修正するときは、この画面を閉じて入力内容を変更してください。
                </p>
            )}
        </DialogContent>
    );
}
