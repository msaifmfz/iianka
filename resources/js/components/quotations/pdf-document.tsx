import { ChevronLeft, ChevronRight, Loader2, Minus, Plus } from 'lucide-react';
import type {
    PDFDocumentProxy,
    PDFDocumentLoadingTask,
    RenderTask,
} from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

export default function PdfDocument({
    url,
    label,
}: {
    url: string;
    label: string;
}) {
    const [pdf, setPdf] = useState<PDFDocumentProxy>();
    const [error, setError] = useState(false);
    const [page, setPage] = useState(1);
    const [zoom, setZoom] = useState(1);

    useEffect(() => {
        let cancelled = false;
        let task: PDFDocumentLoadingTask | undefined;
        const load = async () => {
            try {
                const { getDocument, GlobalWorkerOptions } =
                    await import('pdfjs-dist');

                if (cancelled) {
                    return;
                }

                GlobalWorkerOptions.workerSrc = workerUrl;
                task = getDocument({ url });
                const document = await task.promise;

                if (!cancelled) {
                    setPdf(document);
                }
            } catch {
                if (!cancelled) {
                    setError(true);
                }
            }
        };
        void load();

        return () => {
            cancelled = true;
            void task?.destroy();
        };
    }, [url]);

    return (
        <section
            aria-label={`${label}PDFプレビュー`}
            className="flex h-full min-h-0 flex-col"
        >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-background p-2">
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="前のページ"
                        disabled={!pdf || page === 1}
                        onClick={() => setPage(page - 1)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span
                        aria-live="polite"
                        className="min-w-20 text-center text-sm tabular-nums"
                    >
                        {page} / {pdf?.numPages ?? '—'} ページ
                    </span>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="次のページ"
                        disabled={!pdf || page === pdf.numPages}
                        onClick={() => setPage(page + 1)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="縮小"
                        disabled={zoom <= 0.5}
                        onClick={() => setZoom(zoom - 0.25)}
                    >
                        <Minus className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setZoom(1)}
                        aria-label="幅に合わせる"
                    >
                        {zoom === 1
                            ? '幅に合わせる'
                            : `${Math.round(zoom * 100)}%`}
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="拡大"
                        disabled={zoom >= 2}
                        onClick={() => setZoom(zoom + 0.25)}
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
            </div>
            {error ? (
                <p role="alert" className="p-6 text-sm text-destructive">
                    PDFを表示できませんでした。この画面を閉じて、もう一度「PDFを確認」を押してください。
                </p>
            ) : pdf ? (
                <PdfPage
                    key={page}
                    pdf={pdf}
                    pageNumber={page}
                    label={label}
                    zoom={zoom}
                />
            ) : (
                <div
                    role="status"
                    className="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground"
                >
                    <Loader2 className="size-5 animate-spin" />
                    PDFを読み込み中…
                </div>
            )}
        </section>
    );
}

function PdfPage({
    pdf,
    pageNumber,
    label,
    zoom,
}: {
    pdf: PDFDocumentProxy;
    pageNumber: number;
    label: string;
    zoom: number;
}) {
    const container = useRef<HTMLDivElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const [width, setWidth] = useState(0);
    const [result, setResult] = useState<{
        key: string;
        text?: string;
        error?: boolean;
    }>();
    const key = `${width}:${zoom}`;
    const current = result?.key === key ? result : undefined;

    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(Math.max(1, Math.floor(entry.contentRect.width - 24))),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!width) {
            return;
        }

        let cancelled = false;
        let task: RenderTask | undefined;
        const render = async () => {
            try {
                const page = await pdf.getPage(pageNumber);

                if (cancelled) {
                    return;
                }

                const viewport = page.getViewport({
                    scale:
                        (width * zoom) / page.getViewport({ scale: 1 }).width,
                });
                const density = Math.min(window.devicePixelRatio || 1, 2);
                const buffer = document.createElement('canvas');
                buffer.width = Math.ceil(viewport.width * density);
                buffer.height = Math.ceil(viewport.height * density);
                task = page.render({
                    canvas: buffer,
                    viewport,
                    transform: [density, 0, 0, density, 0, 0],
                });
                await task.promise;
                const content = await page.getTextContent();

                if (cancelled || !canvas.current) {
                    return;
                }

                const target = canvas.current;
                target.width = buffer.width;
                target.height = buffer.height;
                target.style.width = `${viewport.width}px`;
                target.style.height = `${viewport.height}px`;
                target.getContext('2d')?.drawImage(buffer, 0, 0);
                setResult({
                    key,
                    text: content.items
                        // Text items can be individual glyphs; PDF.js already supplies word spaces.
                        .map((item) =>
                            'str' in item
                                ? item.str + (item.hasEOL ? '\n' : '')
                                : '',
                        )
                        .join('')
                        .replaceAll('\u200b', ''),
                });
            } catch {
                if (!cancelled) {
                    setResult({ key, error: true });
                }
            }
        };
        void render();

        return () => {
            cancelled = true;
            task?.cancel();
        };
    }, [pdf, pageNumber, width, zoom, key]);

    return (
        <div
            ref={container}
            className="relative min-h-0 flex-1 overflow-auto overscroll-contain"
            aria-busy={!current}
        >
            {!current && (
                <div
                    role="status"
                    className="sticky top-0 z-10 flex items-center justify-center gap-2 bg-background/95 p-2 text-sm"
                >
                    <Loader2 className="size-4 animate-spin" />
                    ページを表示中…
                </div>
            )}
            {current?.error && (
                <p role="alert" className="p-6 text-sm text-destructive">
                    このページを表示できませんでした。もう一度プレビューを開いてください。
                </p>
            )}
            <div className="w-max min-w-full p-3">
                <canvas
                    ref={canvas}
                    role="img"
                    aria-label={`${label} ${pageNumber}ページ`}
                    data-rendered={Boolean(current?.text)}
                    className="mx-auto block bg-white shadow-sm"
                />
                <p className="sr-only">{current?.text}</p>
            </div>
        </div>
    );
}
