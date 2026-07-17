import { Head, Link, useForm } from '@inertiajs/react';
import {
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Package,
    Pencil,
    Plus,
} from 'lucide-react';
import {
    create as stockCreate,
    edit as stockEdit,
    index as stockIndex,
} from '@/actions/App/Http/Controllers/Admin/StockController';
import { update as purchaseUpdate } from '@/actions/App/Http/Controllers/Admin/StockPurchaseController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import {
    formatStockQuantity,
    isZeroStockQuantity,
    signedStockQuantity,
} from '@/lib/stock';
import { cn } from '@/lib/utils';
import type { FlashResource } from '@/types/ui';

type ReportRow = {
    stock_id: number;
    name: string;
    sku: string | null;
    is_active: boolean;
    allows_fractional_quantity: boolean;
    carry_over: string;
    purchased: string;
    used: string[];
    used_total: string;
    adjustments: string;
    total: string;
};

type ReportBucket = {
    label: string;
    starts_on: string;
    ends_on: string;
};

type ReportTerm = {
    label: string;
    range_label: string;
    term_starts_on: string;
    month_param: string;
    buckets: ReportBucket[];
    rows: ReportRow[];
};

type Props = {
    filters: {
        month: string;
        previous_month: string;
        next_month: string;
        is_current: boolean;
    };
    terms: ReportTerm[];
    today: string;
};

const signedQuantity = signedStockQuantity;
const isZero = isZeroStockQuantity;

function shortDate(isoDate: string) {
    const [, month, day] = isoDate.split('-');

    return `${Number(month)}/${Number(day)}`;
}

function QuantityText({
    value,
    emphasize = false,
}: {
    value: string;
    emphasize?: boolean;
}) {
    return (
        <span
            className={cn(
                'tabular-nums',
                emphasize && 'font-semibold',
                isZero(value) && 'text-muted-foreground/60',
                value.startsWith('-') && 'text-rose-600 dark:text-rose-400',
            )}
        >
            {formatStockQuantity(value)}
        </span>
    );
}

function StockIdentity({ row }: { row: ReportRow }) {
    return (
        <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
                <p
                    className={cn(
                        'truncate font-semibold',
                        !row.is_active && 'text-muted-foreground',
                    )}
                >
                    {row.name}
                </p>
                {!row.is_active && <Badge variant="outline">無効</Badge>}
            </div>
            {/* comment out SKU display as SKU's workflow is not yet implemented */}
            {/* {row.sku && ( */}
            {/*     <p className="truncate text-xs text-muted-foreground"> */}
            {/*         {row.sku} */}
            {/*     </p> */}
            {/* )} */}
        </div>
    );
}

function PurchaseCell({
    row,
    termStartsOn,
    recentResource,
}: {
    row: ReportRow;
    termStartsOn: string;
    recentResource: FlashResource | null;
}) {
    const savedQuantity = formatStockQuantity(row.purchased);
    const { data, setData, put, processing, errors } = useForm({
        term_starts_on: termStartsOn,
        quantity: savedQuantity,
    });
    const isRecent = recentResourceMatches(
        recentResource,
        'stock_purchase_cell',
        `${row.stock_id}-${termStartsOn}`,
    );

    if (!row.is_active) {
        return <QuantityText value={row.purchased} />;
    }

    function save() {
        if (data.quantity.trim() === savedQuantity || processing) {
            return;
        }

        put(purchaseUpdate.url(row.stock_id), { preserveScroll: true });
    }

    return (
        <div className="inline-flex flex-col items-end gap-1">
            <Input
                value={data.quantity}
                onChange={(event) => setData('quantity', event.target.value)}
                onBlur={save}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        event.currentTarget.blur();
                    }
                }}
                inputMode="decimal"
                disabled={processing}
                aria-label={`${row.name} の仕入数`}
                className={cn(
                    'h-8 w-24 text-right tabular-nums',
                    isRecent &&
                        'ring-2 ring-emerald-400/80 dark:ring-emerald-600/80',
                    errors.quantity && 'border-destructive',
                )}
            />
            {errors.quantity && (
                <p className="max-w-40 text-right text-xs text-destructive">
                    {errors.quantity}
                </p>
            )}
        </div>
    );
}

function termContainsToday(term: ReportTerm, today: string) {
    const lastBucket = term.buckets[term.buckets.length - 1];

    return (
        lastBucket !== undefined &&
        today >= term.term_starts_on &&
        today <= lastBucket.ends_on
    );
}

function bucketContainsToday(bucket: ReportBucket, today: string) {
    return today >= bucket.starts_on && today <= bucket.ends_on;
}

function AdjustmentsNote({ value }: { value: string }) {
    if (isZero(value)) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            調整 {signedQuantity(value)}
        </p>
    );
}

function TermCard({
    term,
    today,
    recentResource,
}: {
    term: ReportTerm;
    today: string;
    recentResource: FlashResource | null;
}) {
    const isCurrentTerm = termContainsToday(term, today);
    const cellKey = (row: ReportRow) =>
        `${row.stock_id}-${term.term_starts_on}-${row.purchased}`;

    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b dark:border-neutral-800">
                <div className="flex flex-wrap items-center gap-3">
                    <CardTitle>{term.label}</CardTitle>
                    <span className="text-sm text-muted-foreground">
                        {term.range_label}
                    </span>
                    {isCurrentTerm && <Badge>今月度</Badge>}
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="grid gap-3 p-3 md:hidden">
                    {term.rows.map((row) => (
                        <article
                            key={row.stock_id}
                            className="rounded-2xl border bg-card p-4 shadow-xs dark:border-neutral-800"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <StockIdentity row={row} />
                                <Button asChild variant="outline" size="sm">
                                    <Link href={stockEdit(row.stock_id)}>
                                        <Pencil className="size-4" />
                                        編集
                                    </Link>
                                </Button>
                            </div>
                            <dl className="mt-4 grid gap-2 text-sm">
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        前月繰越
                                    </dt>
                                    <dd>
                                        <QuantityText value={row.carry_over} />
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        仕入
                                    </dt>
                                    <dd>
                                        <PurchaseCell
                                            key={cellKey(row)}
                                            row={row}
                                            termStartsOn={term.term_starts_on}
                                            recentResource={recentResource}
                                        />
                                    </dd>
                                </div>
                                {term.buckets.map((bucket, index) => (
                                    <div
                                        key={bucket.starts_on}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <dt
                                            className={cn(
                                                'text-muted-foreground',
                                                isCurrentTerm &&
                                                    bucketContainsToday(
                                                        bucket,
                                                        today,
                                                    ) &&
                                                    'font-semibold text-amber-700 dark:text-amber-400',
                                            )}
                                        >
                                            使用 {bucket.label}
                                        </dt>
                                        <dd>
                                            <QuantityText
                                                value={row.used[index] ?? '0'}
                                            />
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex items-center justify-between gap-3 border-t pt-2 dark:border-neutral-800">
                                    <dt className="text-muted-foreground">
                                        残数
                                    </dt>
                                    <dd className="text-right">
                                        <QuantityText
                                            value={row.total}
                                            emphasize
                                        />
                                        <AdjustmentsNote
                                            value={row.adjustments}
                                        />
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    ))}
                </div>

                <div className="hidden overflow-x-auto md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-neutral-50 text-left text-xs font-semibold text-muted-foreground uppercase dark:bg-neutral-950">
                            <tr>
                                <th
                                    rowSpan={2}
                                    className="sticky left-0 z-10 bg-neutral-50 px-5 py-3 dark:bg-neutral-950"
                                >
                                    在庫
                                </th>
                                <th
                                    rowSpan={2}
                                    className="px-4 py-3 text-right"
                                >
                                    前月繰越
                                </th>
                                <th
                                    rowSpan={2}
                                    className="px-4 py-3 text-right"
                                >
                                    仕入
                                </th>
                                <th
                                    colSpan={3}
                                    className="border-b px-4 pt-3 pb-2 text-center dark:border-neutral-800"
                                >
                                    使用量
                                </th>
                                <th
                                    rowSpan={2}
                                    className="px-4 py-3 text-right"
                                >
                                    使用計
                                </th>
                                <th
                                    rowSpan={2}
                                    className="px-4 py-3 text-right"
                                >
                                    残数
                                </th>
                                <th rowSpan={2} className="px-4 py-3" />
                            </tr>
                            <tr>
                                {term.buckets.map((bucket) => {
                                    const isCurrentBucket =
                                        isCurrentTerm &&
                                        bucketContainsToday(bucket, today);

                                    return (
                                        <th
                                            key={bucket.starts_on}
                                            className={cn(
                                                'px-4 py-2 text-right font-medium',
                                                isCurrentBucket &&
                                                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                                            )}
                                        >
                                            <span className="block normal-case">
                                                {bucket.label}
                                            </span>
                                            <span className="block text-[11px] font-normal normal-case opacity-80">
                                                {shortDate(bucket.starts_on)}〜
                                                {shortDate(bucket.ends_on)}
                                            </span>
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody className="divide-y dark:divide-neutral-800">
                            {term.rows.map((row) => (
                                <tr
                                    key={row.stock_id}
                                    className="transition hover:bg-neutral-50/80 motion-reduce:transition-none dark:hover:bg-neutral-900/60"
                                >
                                    <td className="sticky left-0 z-10 max-w-56 bg-card px-5 py-3">
                                        <StockIdentity row={row} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <QuantityText value={row.carry_over} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <PurchaseCell
                                            key={cellKey(row)}
                                            row={row}
                                            termStartsOn={term.term_starts_on}
                                            recentResource={recentResource}
                                        />
                                    </td>
                                    {term.buckets.map((bucket, index) => (
                                        <td
                                            key={bucket.starts_on}
                                            className="px-4 py-3 text-right"
                                        >
                                            <QuantityText
                                                value={row.used[index] ?? '0'}
                                            />
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 text-right">
                                        <QuantityText value={row.used_total} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <QuantityText
                                            value={row.total}
                                            emphasize
                                        />
                                        <AdjustmentsNote
                                            value={row.adjustments}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                href={stockEdit(row.stock_id)}
                                                aria-label={`${row.name} を編集`}
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {term.rows.length === 0 && (
                    <div className="p-10 text-center text-sm text-muted-foreground">
                        <Package className="mx-auto mb-3 size-8 opacity-40" />
                        在庫が登録されていません。「在庫を追加」から登録してください。
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdminStocksIndex({ filters, terms, today }: Props) {
    const recentResource = useRecentResource();

    return (
        <>
            <Head title="在庫管理" />
            <div className="mx-auto max-w-7xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Admin Stock Management
                        </p>
                        <h1 className="text-2xl font-bold">在庫管理</h1>
                    </div>
                    <Button asChild>
                        <Link href={stockCreate()}>
                            <Plus className="size-4" />
                            在庫を追加
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={stockIndex({
                                query: { month: filters.previous_month },
                            })}
                            preserveScroll
                        >
                            <ChevronLeft className="size-4" />
                            前月度
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={stockIndex({
                                query: { month: filters.next_month },
                            })}
                            preserveScroll
                        >
                            翌月度
                            <ChevronRight className="size-4" />
                        </Link>
                    </Button>
                    {!filters.is_current && (
                        <Button asChild variant="ghost" size="sm">
                            <Link href={stockIndex()} preserveScroll>
                                <CalendarRange className="size-4" />
                                今月度へ戻る
                            </Link>
                        </Button>
                    )}
                    <p className="ml-auto text-sm text-muted-foreground">
                        仕入欄は入力後、Enter または欄外クリックで保存されます。
                    </p>
                </div>

                {terms.map((term) => (
                    <TermCard
                        key={term.term_starts_on}
                        term={term}
                        today={today}
                        recentResource={recentResource}
                    />
                ))}
            </div>
        </>
    );
}

AdminStocksIndex.layout = {
    breadcrumbs: [
        {
            title: '在庫管理',
            href: stockIndex(),
        },
    ],
};
