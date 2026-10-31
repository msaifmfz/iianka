import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/QuotationController';
import quotationPdf from '@/actions/App/Http/Controllers/QuotationPdfController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { yen } from '@/lib/quotation';

type Row = {
    id: number;
    number: string;
    client_name: string;
    project_name: string;
    issued_on: string;
    total: string;
    item_count: number;
};
type Props = {
    quotations: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
    };
    search: string;
    canManage: boolean;
};
export default function QuotationIndex({
    quotations,
    search,
    canManage,
}: Props) {
    const [query, setQuery] = useState(search);

    return (
        <>
            <Head title="見積書" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6 xl:p-8">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">見積書</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            見積書と内訳書を作成・管理します。
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                見積書を作成
                            </Link>
                        </Button>
                    )}
                </header>
                <form
                    className="flex max-w-xl gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            index.url(),
                            { search: query },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        aria-label="見積書を検索"
                        placeholder="得意先・工事名・見積番号で検索"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <Button variant="outline" type="submit">
                        <Search className="size-4" />
                        検索
                    </Button>
                </form>
                <div className="space-y-3">
                    {quotations.data.map((quote) => (
                        <article
                            key={quote.id}
                            className="rounded-xl border bg-card p-5"
                        >
                            <Link
                                href={show(quote.id)}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-md hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">
                                        {quote.number} · {quote.issued_on}
                                    </p>
                                    <h2 className="mt-1 font-semibold break-words">
                                        {quote.client_name}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {quote.project_name}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-lg font-semibold tabular-nums">
                                        {yen(quote.total)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        税別 · {quote.item_count}項目
                                    </p>
                                </div>
                            </Link>
                            <div className="mt-4 flex flex-wrap gap-2 border-t pt-4">
                                {(['quotation', 'breakdown'] as const).map(
                                    (document) => (
                                        <Button
                                            key={document}
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={quotationPdf.url({
                                                    quotation: quote.id,
                                                    document,
                                                })}
                                            >
                                                <Download className="size-4" />
                                                {document === 'quotation'
                                                    ? '見積書PDF'
                                                    : '内訳書PDF'}
                                            </a>
                                        </Button>
                                    ),
                                )}
                            </div>
                        </article>
                    ))}
                </div>
                {quotations.data.length === 0 && (
                    <div className="rounded-xl border border-dashed p-12 text-center">
                        <FileText className="mx-auto mb-4 size-10 text-muted-foreground" />
                        <h2 className="font-semibold">
                            {search
                                ? '一致する見積書がありません'
                                : '最初の見積書を作成しましょう'}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {search
                                ? '検索条件を変えてお試しください。'
                                : '品目と施工条件を選ぶと、金額が自動で計算されます。'}
                        </p>
                    </div>
                )}
                {quotations.last_page > 1 && (
                    <nav
                        aria-label="ページ切替"
                        className="flex items-center justify-center gap-3"
                    >
                        <Button
                            variant="outline"
                            disabled={quotations.current_page === 1}
                            onClick={() =>
                                router.get(index.url(), {
                                    search,
                                    page: quotations.current_page - 1,
                                })
                            }
                        >
                            前へ
                        </Button>
                        <span className="text-sm">
                            {quotations.current_page} / {quotations.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={
                                quotations.current_page === quotations.last_page
                            }
                            onClick={() =>
                                router.get(index.url(), {
                                    search,
                                    page: quotations.current_page + 1,
                                })
                            }
                        >
                            次へ
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}
QuotationIndex.layout = { breadcrumbs: [{ title: '見積書', href: index() }] };
