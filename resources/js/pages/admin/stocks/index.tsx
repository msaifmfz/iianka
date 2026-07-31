import { Head, Link, useForm } from '@inertiajs/react';
import {
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Info,
    Minus,
    Package,
    Pencil,
    Plus,
    Save,
    StickyNote,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    create as stockCreate,
    edit as stockEdit,
    index as stockIndex,
} from '@/actions/App/Http/Controllers/Admin/StockController';
import { store as purchaseStore } from '@/actions/App/Http/Controllers/Admin/StockPurchaseController';
import { store as purchaseCorrectionStore } from '@/actions/App/Http/Controllers/Admin/StockPurchaseCorrectionController';
import { update as termMemoUpdate } from '@/actions/App/Http/Controllers/Admin/StockTermMemoController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
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
    memo: string | null;
    previous_memo: string | null;
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
    previous_term_label: string;
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
            {/* SKU is intentionally hidden until its workflow is implemented. */}
        </div>
    );
}

function PurchaseCorrectionDialog({
    row,
    termStartsOn,
    instanceId,
}: {
    row: ReportRow;
    termStartsOn: string;
    instanceId: 'mobile' | 'desktop';
}) {
    const [open, setOpen] = useState(false);
    const hasZeroPurchasedTotal = isZero(row.purchased);
    const hasNegativePurchasedTotal =
        !hasZeroPurchasedTotal && row.purchased.startsWith('-');
    const { data, setData, post, processing, errors, resetAndClearErrors } =
        useForm({
            term_starts_on: termStartsOn,
            quantity_to_subtract: '',
        });

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen && !processing) {
            resetAndClearErrors('quantity_to_subtract');
        }

        if (!processing) {
            setOpen(nextOpen);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (processing || data.quantity_to_subtract.trim() === '') {
            return;
        }

        post(purchaseCorrectionStore.url(row.stock_id), {
            preserveScroll: true,
            onSuccess: () => {
                resetAndClearErrors();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs text-muted-foreground"
                    aria-label={`${row.name} の仕入を訂正`}
                >
                    <Minus className="size-3.5" />
                    仕入訂正
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>{row.name} の仕入訂正</DialogTitle>
                        <DialogDescription>
                            入力した正の数量を、この月度の仕入計と現在庫から差し引きます。仕入計を超える数量も入力できます。
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-lg border bg-muted/40 p-3 text-sm dark:border-neutral-800">
                        <span className="text-muted-foreground">
                            現在の仕入計
                        </span>
                        <span className="float-right font-semibold tabular-nums">
                            {formatStockQuantity(row.purchased)}
                        </span>
                    </div>

                    {(hasZeroPurchasedTotal || hasNegativePurchasedTotal) && (
                        <div
                            role="note"
                            aria-label="仕入訂正の注意"
                            className="rounded-md border border-amber-200/80 bg-amber-50/70 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/80 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            {hasZeroPurchasedTotal
                                ? '現在の仕入計は0です。入力した数量分がマイナスの仕入計として記録されます。'
                                : '現在の仕入計はすでにマイナスです。入力した数量分だけさらに減少します。'}
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label
                            htmlFor={`purchase-correction-${instanceId}-${row.stock_id}-${termStartsOn}`}
                        >
                            差し引く数量
                        </Label>
                        <Input
                            id={`purchase-correction-${instanceId}-${row.stock_id}-${termStartsOn}`}
                            value={data.quantity_to_subtract}
                            onChange={(event) =>
                                setData(
                                    'quantity_to_subtract',
                                    event.target.value,
                                )
                            }
                            inputMode="decimal"
                            autoComplete="off"
                            autoFocus
                            disabled={processing}
                            aria-invalid={
                                errors.quantity_to_subtract ? 'true' : undefined
                            }
                            placeholder={
                                row.allows_fractional_quantity
                                    ? '例: 1.5'
                                    : '例: 1'
                            }
                        />
                        {errors.quantity_to_subtract && (
                            <p className="text-sm text-destructive">
                                {errors.quantity_to_subtract}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            0より大きい数量を入力してください。訂正後の仕入計と現在庫はマイナスになる場合があります。
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            キャンセル
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={
                                processing ||
                                data.quantity_to_subtract.trim() === ''
                            }
                        >
                            {processing ? '訂正中…' : '訂正を確定'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PurchaseControls({
    row,
    termStartsOn,
    recentResource,
    instanceId,
}: {
    row: ReportRow;
    termStartsOn: string;
    recentResource: FlashResource | null;
    instanceId: 'mobile' | 'desktop';
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        term_starts_on: termStartsOn,
        quantity_to_add: '',
    });
    const isRecent = recentResourceMatches(
        recentResource,
        'stock_purchase_cell',
        `${row.stock_id}-${termStartsOn}`,
    );
    const inputId = `purchase-addition-${instanceId}-${row.stock_id}-${termStartsOn}`;
    const errorId = `${inputId}-error`;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (processing || data.quantity_to_add.trim() === '') {
            return;
        }

        post(purchaseStore.url(row.stock_id), {
            preserveScroll: true,
            onSuccess: () => reset('quantity_to_add'),
        });
    }

    return (
        <div className="flex min-w-48 flex-col items-end gap-1.5">
            <div
                className={cn(
                    'rounded px-1.5 py-0.5',
                    isRecent &&
                        'ring-2 ring-emerald-400/80 dark:ring-emerald-600/80',
                )}
            >
                <span className="mr-2 text-xs text-muted-foreground">
                    仕入計
                </span>
                <QuantityText value={row.purchased} emphasize />
            </div>

            {row.is_active ? (
                <form
                    onSubmit={submit}
                    className="flex flex-wrap justify-end gap-1.5"
                >
                    <Input
                        id={inputId}
                        value={data.quantity_to_add}
                        onChange={(event) =>
                            setData('quantity_to_add', event.target.value)
                        }
                        inputMode="decimal"
                        autoComplete="off"
                        disabled={processing}
                        aria-label={`${row.name} の追加仕入数`}
                        aria-describedby={
                            errors.quantity_to_add ? errorId : undefined
                        }
                        aria-invalid={
                            errors.quantity_to_add ? 'true' : undefined
                        }
                        placeholder="追加数"
                        className="h-8 w-24 text-right tabular-nums"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        aria-label={`${row.name} に仕入を追加`}
                        disabled={
                            processing || data.quantity_to_add.trim() === ''
                        }
                    >
                        <Plus className="size-3.5" />
                        {processing ? '追加中…' : '追加'}
                    </Button>
                    {errors.quantity_to_add && (
                        <p
                            id={errorId}
                            className="w-full max-w-52 text-right text-xs text-destructive"
                        >
                            {errors.quantity_to_add}
                        </p>
                    )}
                </form>
            ) : (
                <p className="text-xs text-muted-foreground">
                    無効な在庫には追加できません
                </p>
            )}

            <PurchaseCorrectionDialog
                row={row}
                termStartsOn={termStartsOn}
                instanceId={instanceId}
            />
        </div>
    );
}

function ReadOnlyMemo({ memo }: { memo: string | null }) {
    if (!memo) {
        return <span className="text-xs text-muted-foreground">メモなし</span>;
    }

    return (
        <p className="max-h-24 overflow-y-auto text-sm leading-5 whitespace-pre-wrap">
            {memo}
        </p>
    );
}

function MemoDialog({
    row,
    term,
    recentResource,
    instanceId,
}: {
    row: ReportRow;
    term: ReportTerm;
    recentResource: FlashResource | null;
    instanceId: 'mobile' | 'desktop';
}) {
    const [open, setOpen] = useState(false);
    const {
        data,
        setData,
        put,
        processing,
        errors,
        isDirty,
        recentlySuccessful,
        setDefaults,
        resetAndClearErrors,
    } = useForm({
        term_starts_on: term.term_starts_on,
        memo: row.memo ?? '',
    });
    const { confirm, dialog: discardDialog } = useConfirmDialog();
    const textareaId = `stock-memo-${instanceId}-${row.stock_id}-${term.term_starts_on}`;
    const isRecent = recentResourceMatches(
        recentResource,
        'stock_term_memo',
        `${row.stock_id}-${term.term_starts_on}`,
    );

    async function closeDialog() {
        if (processing) {
            return;
        }

        if (
            isDirty &&
            !(await confirm({
                title: '未保存のメモを破棄しますか？',
                description: '保存していない変更は元に戻せません。',
                confirmLabel: '破棄する',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        resetAndClearErrors();
        setOpen(false);
    }

    function handleOpenChange(nextOpen: boolean) {
        if (nextOpen) {
            setOpen(true);

            return;
        }

        void closeDialog();
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (processing || !isDirty) {
            return;
        }

        const savedMemo = data.memo.trim();

        put(termMemoUpdate.url(row.stock_id), {
            preserveScroll: true,
            onSuccess: () => {
                setData('memo', savedMemo);
                setDefaults('memo', savedMemo);
            },
        });
    }

    return (
        <>
            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className={cn(
                            'h-auto min-h-8 w-full max-w-52 justify-start px-2.5 py-1.5 font-normal',
                            isRecent &&
                                'ring-2 ring-emerald-400/80 dark:ring-emerald-600/80',
                        )}
                        aria-label={`${row.name} の${term.label}メモを編集`}
                    >
                        <StickyNote className="size-4 shrink-0" />
                        <span
                            className={cn(
                                'line-clamp-2 min-w-0 text-left whitespace-pre-wrap',
                                !row.memo && 'text-muted-foreground',
                            )}
                        >
                            {row.memo || 'メモを入力'}
                        </span>
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={submit} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>{row.name} の月度メモ</DialogTitle>
                            <DialogDescription>
                                前月度を確認しながら、表示中の月度のメモを編集できます。
                            </DialogDescription>
                        </DialogHeader>

                        <section className="grid gap-2">
                            <p className="text-sm font-medium">
                                {term.previous_term_label}
                                <Badge variant="outline" className="ml-2">
                                    参照のみ
                                </Badge>
                            </p>
                            <div className="max-h-32 min-h-16 overflow-y-auto rounded-lg border bg-muted/40 p-3 dark:border-neutral-800">
                                <ReadOnlyMemo memo={row.previous_memo} />
                            </div>
                        </section>

                        <section className="grid gap-2">
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor={textareaId}>
                                    {term.label}メモ
                                </Label>
                                {isDirty && (
                                    <span className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                        未保存
                                    </span>
                                )}
                                {!isDirty && recentlySuccessful && (
                                    <span
                                        role="status"
                                        className="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        保存しました
                                    </span>
                                )}
                            </div>
                            <Textarea
                                id={textareaId}
                                value={data.memo}
                                onChange={(event) =>
                                    setData('memo', event.target.value)
                                }
                                disabled={processing}
                                aria-invalid={errors.memo ? 'true' : undefined}
                                placeholder="引き継ぎ事項や発注状況などを入力"
                                className="min-h-32 resize-y"
                            />
                            {errors.memo && (
                                <p className="text-sm text-destructive">
                                    {errors.memo}
                                </p>
                            )}
                        </section>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void closeDialog()}
                                disabled={processing}
                            >
                                閉じる
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || !isDirty}
                            >
                                <Save className="size-4" />
                                {processing ? '保存中…' : 'メモを保存'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            {discardDialog}
        </>
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
    const isFutureTerm = term.term_starts_on > today;
    const purchaseKey = (row: ReportRow) =>
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
                    {isFutureTerm && (
                        <Badge
                            variant="outline"
                            className="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            予定月度
                        </Badge>
                    )}
                    <Badge>編集対象</Badge>
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
                                <div className="flex items-start justify-between gap-3">
                                    <dt className="pt-1 text-muted-foreground">
                                        仕入
                                    </dt>
                                    <dd>
                                        <PurchaseControls
                                            key={purchaseKey(row)}
                                            row={row}
                                            termStartsOn={term.term_starts_on}
                                            recentResource={recentResource}
                                            instanceId="mobile"
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

                            <div className="mt-4 grid gap-2 border-t pt-3 dark:border-neutral-800">
                                <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <StickyNote className="size-3.5" />
                                    月度メモ
                                </p>
                                <MemoDialog
                                    row={row}
                                    term={term}
                                    recentResource={recentResource}
                                    instanceId="mobile"
                                />
                            </div>
                        </article>
                    ))}
                </div>

                <div className="hidden overflow-x-auto md:block">
                    <table className="w-full min-w-[84rem] text-sm">
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
                                    仕入計・追加
                                </th>
                                <th
                                    colSpan={term.buckets.length}
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
                                <th rowSpan={2} className="px-4 py-3">
                                    メモ
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
                                    <td className="px-4 py-3 text-right align-top">
                                        <PurchaseControls
                                            key={purchaseKey(row)}
                                            row={row}
                                            termStartsOn={term.term_starts_on}
                                            recentResource={recentResource}
                                            instanceId="desktop"
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
                                    <td className="w-56 px-4 py-3 align-top normal-case">
                                        <MemoDialog
                                            row={row}
                                            term={term}
                                            recentResource={recentResource}
                                            instanceId="desktop"
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right align-top">
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
    const hasFutureTerm = terms.some((term) => term.term_starts_on > today);

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
                        追加する仕入数を入力し、「追加」を押して反映します。
                    </p>
                </div>

                {hasFutureTerm && (
                    <div
                        role="note"
                        className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <p>
                            予定月度の仕入も、追加した時点で現在庫に反映されます。
                        </p>
                    </div>
                )}

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
