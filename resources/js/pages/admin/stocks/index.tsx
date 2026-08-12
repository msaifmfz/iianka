import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Info,
    ListOrdered,
    Minus,
    Package,
    Pencil,
    Plus,
    RotateCcw,
    Save,
    StickyNote,
} from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { FormEvent } from 'react';
import {
    create as stockCreate,
    edit as stockEdit,
    index as stockIndex,
} from '@/actions/App/Http/Controllers/Admin/StockController';
import stockOrderUpdate from '@/actions/App/Http/Controllers/Admin/StockOrderController';
import { store as purchaseStore } from '@/actions/App/Http/Controllers/Admin/StockPurchaseController';
import { store as purchaseCorrectionStore } from '@/actions/App/Http/Controllers/Admin/StockPurchaseCorrectionController';
import { update as termMemoUpdate } from '@/actions/App/Http/Controllers/Admin/StockTermMemoController';
import { show as constructionScheduleShow } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    ScheduleDetailDialog,
    useScheduleDetailHold,
} from '@/components/schedule-detail-dialog';
import type { ScheduleDetailEvent } from '@/components/schedule-detail-dialog';
import SortableOrderList from '@/components/sortable-order-list';
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
import { useIndexScrollRestore } from '@/hooks/use-index-scroll-restore';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import { constructionScheduleStatusLabel } from '@/lib/schedule-status';
import {
    formatStockQuantity,
    isZeroStockQuantity,
    signedStockQuantity,
} from '@/lib/stock';
import { cn } from '@/lib/utils';
import { fieldOrElementError } from '@/lib/validation';
import type { ConstructionScheduleStatus } from '@/types';
import type { FlashResource } from '@/types/ui';

type UsageScheduleReference = {
    schedule_id: number;
    quantity: string;
};

type UsageSchedulePreview = ScheduleDetailEvent & {
    type: 'construction';
    scheduled_on: string;
    status: ConstructionScheduleStatus;
};

type UsageSchedulePreviews = Record<string, UsageSchedulePreview>;

type UsageScheduleContextValue = {
    /** Undefined until the follow-up request resolves; see useUsageSchedulePreviews. */
    previews?: UsageSchedulePreviews;
    requestPreviews: () => void;
    returnTo: string;
    onOpenDetail: (schedule: ScheduleDetailEvent) => void;
};

const UsageScheduleContext = createContext<UsageScheduleContextValue | null>(
    null,
);

function useUsageScheduleContext() {
    const context = useContext(UsageScheduleContext);

    if (context === null) {
        throw new Error(
            'Usage schedule components must be rendered inside UsageScheduleContext.',
        );
    }

    return context;
}

/**
 * The schedule details behind the usage links. They are an optional prop, so
 * they are fetched the first time a usage bucket is opened rather than on
 * every visit — most visits never open one. Remembering which URL was asked
 * keeps a second dialog from firing the same request, while still re-arming
 * when the term changes.
 */
function useUsageSchedulePreviews(
    url: string,
    previews?: UsageSchedulePreviews,
) {
    const requestedUrl = useRef<string | null>(null);

    return useCallback(
        function requestPreviews() {
            if (previews !== undefined || requestedUrl.current === url) {
                return;
            }

            requestedUrl.current = url;
            router.reload({ only: ['usageSchedulePreviews'] });
        },
        [previews, url],
    );
}

type ReportRow = {
    stock_id: number;
    name: string;
    sku: string | null;
    is_active: boolean;
    allows_fractional_quantity: boolean;
    carry_over: string;
    purchased: string;
    used: string[];
    usage_schedules: Record<string, UsageScheduleReference[]>;
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

type StockOrderItem = {
    id: number;
    name: string;
    is_active: boolean;
};

type Props = {
    filters: {
        month: string;
        previous_month: string;
        next_month: string;
        is_current: boolean;
    };
    terms: ReportTerm[];
    /** Optional: absent until a usage bucket is opened; see useUsageSchedulePreviews. */
    usageSchedulePreviews?: UsageSchedulePreviews;
    stockOrder: StockOrderItem[];
    today: string;
};

const stockIndexScrollStorageKey = 'admin-stocks:index-scroll:';
const signedQuantity = signedStockQuantity;
const isZero = isZeroStockQuantity;

function stockOrderIds(stocks: StockOrderItem[]): number[] {
    return stocks.map((stock) => stock.id);
}

function stockOrderIdsAreEqual(
    firstIds: number[],
    secondIds: number[],
): boolean {
    return (
        firstIds.length === secondIds.length &&
        firstIds.every((id, index) => id === secondIds[index])
    );
}

function normalizeStockOrderIds(
    pendingIds: number[] | null,
    serverIds: number[],
): number[] {
    if (pendingIds === null) {
        return serverIds;
    }

    const serverIdSet = new Set(serverIds);
    const preservedIds = pendingIds.filter((id) => serverIdSet.has(id));
    const preservedIdSet = new Set(preservedIds);

    return [
        ...preservedIds,
        ...serverIds.filter((id) => !preservedIdSet.has(id)),
    ];
}

function orderStocksByIds(
    stocks: StockOrderItem[],
    orderedIds: number[],
): StockOrderItem[] {
    const stocksById = new Map(stocks.map((stock) => [stock.id, stock]));

    return orderedIds.flatMap((id) => {
        const stock = stocksById.get(id);

        return stock ? [stock] : [];
    });
}

function getStockOrderId(stock: StockOrderItem): number {
    return stock.id;
}

function getStockOrderLabel(stock: StockOrderItem): string {
    return stock.name;
}

function StockOrderDialog({ stocks }: { stocks: StockOrderItem[] }) {
    const [open, setOpen] = useState(false);
    const [pendingIds, setPendingIds] = useState<number[] | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [orderError, setOrderError] = useState<string | null>(null);
    const serverIds = stockOrderIds(stocks);
    const orderedIds = normalizeStockOrderIds(pendingIds, serverIds);
    const orderedStocks = orderStocksByIds(stocks, orderedIds);
    const isDirty = !stockOrderIdsAreEqual(orderedIds, serverIds);

    function handleOpenChange(nextOpen: boolean) {
        if (isSaving) {
            return;
        }

        setOpen(nextOpen);

        if (!nextOpen) {
            setPendingIds(null);
            setOrderError(null);
        }
    }

    function resetOrder() {
        setPendingIds(null);
        setOrderError(null);
    }

    function saveOrder() {
        if (!isDirty || isSaving) {
            return;
        }

        setIsSaving(true);
        setOrderError(null);

        router.patch(
            stockOrderUpdate.url(),
            { ordered_ids: orderedIds },
            {
                only: ['stockOrder', 'terms'],
                preserveScroll: true,
                onError: (errors) =>
                    setOrderError(
                        fieldOrElementError(errors, 'ordered_ids') ??
                            '表示順を保存できませんでした。',
                    ),
                onFinish: () => setIsSaving(false),
                onSuccess: () => {
                    setPendingIds(null);
                    setOrderError(null);
                    setOpen(false);
                },
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    disabled={stocks.length < 2}
                >
                    <ListOrdered className="size-4" />
                    表示順を変更
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>在庫の表示順</DialogTitle>
                    <DialogDescription>
                        ドラッグして並べ替えます。キーボードでは、並べ替えボタンにフォーカスしてスペースキーを押してください。
                    </DialogDescription>
                </DialogHeader>

                {orderError && (
                    <div
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {orderError}
                    </div>
                )}

                <div className="max-h-[58vh] overflow-y-auto pr-1">
                    <SortableOrderList
                        className="space-y-2"
                        disabled={isSaving}
                        getId={getStockOrderId}
                        getLabel={getStockOrderLabel}
                        items={orderedStocks}
                        onReorder={(nextStocks) => {
                            setPendingIds(stockOrderIds(nextStocks));
                            setOrderError(null);
                        }}
                        renderItem={(
                            stock,
                            { dragHandle, index, isDragging },
                        ) => (
                            <div
                                data-stock-order-id={stock.id}
                                className={cn(
                                    'flex items-center gap-2 rounded-lg border bg-background p-2 transition-shadow',
                                    isDragging &&
                                        'shadow-lg ring-2 ring-ring/20',
                                )}
                            >
                                {dragHandle}
                                <Badge
                                    variant="outline"
                                    className="h-9 min-w-11 justify-center"
                                >
                                    #{index + 1}
                                </Badge>
                                <span
                                    className={cn(
                                        'min-w-0 flex-1 truncate font-medium',
                                        !stock.is_active &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {stock.name}
                                </span>
                                <Badge
                                    variant={
                                        stock.is_active
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {stock.is_active ? '有効' : '無効'}
                                </Badge>
                            </div>
                        )}
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={resetOrder}
                        disabled={!isDirty || isSaving}
                    >
                        <RotateCcw className="size-4" />
                        元に戻す
                    </Button>
                    <Button
                        type="button"
                        onClick={saveOrder}
                        disabled={!isDirty || isSaving}
                    >
                        <Save className="size-4" />
                        表示順を保存
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

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

function UsageScheduleItem({
    schedule,
    quantity,
}: {
    schedule: UsageSchedulePreview;
    quantity: string;
}) {
    const { returnTo, onOpenDetail } = useUsageScheduleContext();
    const detailHold = useScheduleDetailHold(onOpenDetail);

    // A button rather than a link: long-pressing an anchor opens the browser's
    // own link menu on touch devices, which would fight the hold-to-preview.
    return (
        <li className="grid grid-cols-[minmax(0,1fr)_auto] items-stretch gap-2 rounded-xl border bg-card p-1.5 dark:border-neutral-800">
            <button
                type="button"
                aria-label={`${schedule.title}の予定ページを開く`}
                className="grid min-w-0 touch-manipulation gap-1 rounded-lg px-3 py-2 text-left transition select-none hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                onPointerDown={(pointerEvent) =>
                    detailHold.startHold(pointerEvent, schedule)
                }
                onPointerMove={detailHold.updateHold}
                onPointerUp={detailHold.finishHold}
                onPointerCancel={detailHold.finishHold}
                onPointerLeave={detailHold.finishHold}
                onContextMenu={(event) => event.preventDefault()}
                onClick={() => {
                    if (detailHold.consumeClickAfterHold()) {
                        return;
                    }

                    router.visit(
                        constructionScheduleShow(schedule.id, {
                            query: { return_to: returnTo },
                        }),
                    );
                }}
            >
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    <span>
                        {shortDate(schedule.scheduled_on)} {schedule.time}
                    </span>
                    <span>#{schedule.schedule_number ?? '?'}</span>
                    <span className="rounded-full bg-muted px-2 py-0.5 font-medium text-foreground">
                        {constructionScheduleStatusLabel(schedule.status)}
                    </span>
                </span>
                <span className="truncate font-semibold">{schedule.title}</span>
                <span className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    {schedule.general_contractor && (
                        <span className="truncate">
                            {schedule.general_contractor}
                        </span>
                    )}
                    <span>使用 {formatStockQuantity(quantity)}</span>
                </span>
            </button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-auto self-stretch px-3 text-xs"
                aria-label={`${schedule.title}の詳細を表示`}
                onClick={() => onOpenDetail(schedule)}
            >
                詳細
            </Button>
        </li>
    );
}

function UsageScheduleItemSkeleton() {
    return (
        <li className="grid gap-2 rounded-xl border bg-card p-1.5 dark:border-neutral-800">
            <div className="grid gap-2 px-3 py-2">
                <div className="h-3 w-32 animate-pulse rounded bg-muted" />
                <div className="h-4 w-48 animate-pulse rounded bg-muted" />
                <div className="h-3 w-24 animate-pulse rounded bg-muted" />
            </div>
        </li>
    );
}

/**
 * A usage the details could not be loaded for: the schedule was deleted or
 * moved out of the term between this page rendering and the details arriving,
 * or the request for them failed. The quantity is still known, and the link
 * still resolves, so the row stays useful instead of pulsing forever.
 */
function UsageScheduleItemFallback({
    reference,
}: {
    reference: UsageScheduleReference;
}) {
    const { returnTo } = useUsageScheduleContext();

    return (
        <li className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 rounded-xl border bg-card p-1.5 dark:border-neutral-800">
            <span className="grid gap-1 px-3 py-2 text-xs text-muted-foreground">
                <span className="font-semibold text-foreground">
                    予定の詳細を取得できませんでした
                </span>
                <span>使用 {formatStockQuantity(reference.quantity)}</span>
            </span>
            <Button asChild variant="ghost" size="sm" className="text-xs">
                <Link
                    href={constructionScheduleShow(reference.schedule_id, {
                        query: { return_to: returnTo },
                    })}
                >
                    予定を開く
                </Link>
            </Button>
        </li>
    );
}

function UsageScheduleList({
    references,
}: {
    references: UsageScheduleReference[];
}) {
    const { previews } = useUsageScheduleContext();

    return (
        <ul className="grid max-h-[min(60vh,32rem)] gap-2 overflow-y-auto pr-1">
            {references.map((reference) => {
                if (previews === undefined) {
                    return (
                        <UsageScheduleItemSkeleton
                            key={reference.schedule_id}
                        />
                    );
                }

                const schedule = previews[String(reference.schedule_id)];

                return schedule === undefined ? (
                    <UsageScheduleItemFallback
                        key={reference.schedule_id}
                        reference={reference}
                    />
                ) : (
                    <UsageScheduleItem
                        key={reference.schedule_id}
                        schedule={schedule}
                        quantity={reference.quantity}
                    />
                );
            })}
        </ul>
    );
}

function UsageSchedulesDialog({
    stockName,
    bucket,
    value,
    references,
}: {
    stockName: string;
    bucket: ReportBucket;
    value: string;
    references: UsageScheduleReference[];
}) {
    const { requestPreviews } = useUsageScheduleContext();

    return (
        <Dialog
            onOpenChange={(open) => {
                if (open) {
                    requestPreviews();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="h-auto gap-1 p-0 text-xs font-semibold text-sky-700 dark:text-sky-300"
                    aria-label={`${stockName}の${bucket.label}の使用予定を確認（${references.length}件）`}
                >
                    <ListOrdered className="size-3.5" />
                    施工 {references.length}件
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{stockName} の使用</DialogTitle>
                    <DialogDescription>
                        {bucket.label}（{shortDate(bucket.starts_on)}〜
                        {shortDate(bucket.ends_on)}）・使用量{' '}
                        {formatStockQuantity(value)}
                        。タップで予定ページを開き、長押しで詳細を確認できます。
                    </DialogDescription>
                </DialogHeader>
                <UsageScheduleList references={references} />
            </DialogContent>
        </Dialog>
    );
}

function UsageQuantity({
    row,
    bucket,
    value,
}: {
    row: ReportRow;
    bucket: ReportBucket;
    value: string;
}) {
    // Driven by the row's own references so the count is right before the
    // schedule details arrive.
    const references = row.usage_schedules[bucket.starts_on] ?? [];

    return (
        <div className="grid justify-items-end gap-1">
            <QuantityText value={value} />
            {references.length > 0 && (
                <UsageSchedulesDialog
                    stockName={row.name}
                    bucket={bucket}
                    value={value}
                    references={references}
                />
            )}
        </div>
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
                                        <dd className="text-right">
                                            <UsageQuantity
                                                row={row}
                                                bucket={bucket}
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
                                            className="px-4 py-3 text-right align-top"
                                        >
                                            <UsageQuantity
                                                row={row}
                                                bucket={bucket}
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

export default function AdminStocksIndex({
    filters,
    terms,
    usageSchedulePreviews,
    stockOrder,
    today,
}: Props) {
    const { url } = usePage();
    const recentResource = useRecentResource();
    const [detailSchedule, setDetailSchedule] =
        useState<ScheduleDetailEvent | null>(null);
    const hasFutureTerm = terms.some((term) => term.term_starts_on > today);
    const requestPreviews = useUsageSchedulePreviews(
        url,
        usageSchedulePreviews,
    );
    const usageScheduleContext = useMemo(
        () => ({
            previews: usageSchedulePreviews,
            requestPreviews,
            returnTo: url,
            onOpenDetail: setDetailSchedule,
        }),
        [usageSchedulePreviews, requestPreviews, url],
    );

    useIndexScrollRestore(stockIndexScrollStorageKey, url);

    return (
        <UsageScheduleContext value={usageScheduleContext}>
            <Head title="在庫管理" />
            <div className="mx-auto max-w-7xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Admin Stock Management
                        </p>
                        <h1 className="text-2xl font-bold">在庫管理</h1>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StockOrderDialog stocks={stockOrder} />
                        <Button asChild>
                            <Link href={stockCreate()}>
                                <Plus className="size-4" />
                                在庫を追加
                            </Link>
                        </Button>
                    </div>
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
            <ScheduleDetailDialog
                event={detailSchedule}
                open={detailSchedule !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailSchedule(null);
                    }
                }}
                description="使用の内容を確認できます。"
            >
                {detailSchedule !== null && (
                    <Button asChild className="w-full">
                        <Link
                            href={constructionScheduleShow(detailSchedule.id, {
                                query: { return_to: url },
                            })}
                            prefetch
                        >
                            予定ページを開く
                        </Link>
                    </Button>
                )}
            </ScheduleDetailDialog>
        </UsageScheduleContext>
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
