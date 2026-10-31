import { http, HttpResponseError } from '@inertiajs/core';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Check,
    ChevronDown,
    ChevronUp,
    FileText,
    Loader2,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    index,
    preview,
    store,
    update,
} from '@/actions/App/Http/Controllers/QuotationController';
import FormField from '@/components/form-field';
import ItemEditor from '@/components/quotations/item-editor';
import PdfPreview from '@/components/quotations/pdf-preview';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { blankQuotationItem, quotationSelectClass, yen } from '@/lib/quotation';
import { cn } from '@/lib/utils';
import type {
    CatalogItem,
    Conditions,
    Quotation,
    QuotationCalculation,
    QuotationInput,
    QuotationItem,
} from '@/types/quotation';

type Props = {
    quotation: Quotation | null;
    catalog: CatalogItem[];
    conditions: Conditions;
    categories: string[];
    today: string;
    defaultPerson: string;
};
type PreviewState = {
    key: string;
    calculation?: QuotationCalculation;
    errors?: Record<string, string>;
    message?: string;
};

export default function QuotationForm({
    quotation,
    catalog,
    conditions,
    categories,
    today,
    defaultPerson,
}: Props) {
    const form = useForm<QuotationInput>({
        version: quotation?.version ?? 1,
        client_name: '',
        project_name: '',
        delivery_location: '',
        person_in_charge: defaultPerson,
        issued_on: today,
        category: categories[0],
        expenses: '0',
        small_job: false,
        minimum_charge: '10000',
        holiday: false,
        net_enabled: false,
        net_multiplier: '1',
        display_mode: 'detail',
        notes: '',
        items: [blankQuotationItem(conditions)],
        ...quotation?.input,
    });
    const [selected, setSelected] = useState(0);
    const [previewState, setPreviewState] = useState<PreviewState | null>(null);
    const [retry, setRetry] = useState(0);
    const payload = JSON.stringify(form.data);
    const current = previewState?.key === payload ? previewState : null;
    const [lastCalculation, setLastCalculation] = useState<
        QuotationCalculation | undefined
    >(quotation?.calculation);
    const calculation = current?.calculation ?? lastCalculation;
    const [pdfInput, setPdfInput] = useState<QuotationInput | null>(null);
    const canIssue =
        Boolean(current?.calculation) &&
        (
            [
                'client_name',
                'project_name',
                'person_in_charge',
                'issued_on',
            ] as const
        ).every((field) => (form.data[field] ?? '').trim() !== '');
    const errors: Record<string, string> = {
        ...previewState?.errors,
        ...form.errors,
    };
    const waiting = current === null;
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const loadPreview = async () => {
            try {
                const response = await http.getClient().request({
                    method: 'post',
                    url: preview.url(quotation?.id),
                    data: JSON.parse(payload),
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!controller.signal.aborted) {
                    const result = JSON.parse(
                        response.data,
                    ) as QuotationCalculation;
                    setLastCalculation(result);
                    setPreviewState({ key: payload, calculation: result });
                }
            } catch (error) {
                if (controller.signal.aborted) {
                    return;
                }

                if (
                    error instanceof HttpResponseError &&
                    error.response.status === 422
                ) {
                    const body = JSON.parse(error.response.data) as {
                        errors: Record<string, string[]>;
                    };
                    setPreviewState({
                        key: payload,
                        errors: Object.fromEntries(
                            Object.entries(body.errors).map(([key, value]) => [
                                key,
                                value[0],
                            ]),
                        ),
                    });
                } else {
                    setPreviewState({
                        key: payload,
                        message:
                            '金額を計算できませんでした。接続を確認して再試行してください。',
                    });
                }
            }
        };
        const timer = window.setTimeout(() => {
            void loadPreview();
        }, 350);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [payload, quotation?.id, retry]);

    useEffect(() => {
        if (!form.isDirty || saving) {
            return;
        }

        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', beforeUnload);
        const remove = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                !event.detail.visit.prefetch &&
                !window.confirm(
                    '未保存の変更があります。この画面を離れますか？',
                )
            ) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            remove();
        };
    }, [form.isDirty, saving]);

    function changeItem(index: number, item: QuotationItem) {
        form.clearErrors();
        form.setData(
            'items',
            form.data.items.map((existing, i) =>
                i === index ? item : existing,
            ),
        );
    }
    function addItem() {
        if (form.data.items.length >= 30) {
            return;
        }

        setSelected(form.data.items.length);
        form.setData('items', [
            ...form.data.items,
            blankQuotationItem(conditions),
        ]);
    }
    function removeItem(i: number) {
        const item = form.data.items[i];

        if (
            (item.catalog_id || item.free_name) &&
            !window.confirm('この項目を削除しますか？')
        ) {
            return;
        }

        const next = form.data.items.filter((_, n) => n !== i);
        form.setData(
            'items',
            next.length ? next : [blankQuotationItem(conditions)],
        );
        setSelected(Math.max(0, i - 1));
        form.clearErrors();
    }
    function moveItem(i: number, direction: number) {
        const next = [...form.data.items];
        [next[i], next[i + direction]] = [next[i + direction], next[i]];
        form.setData('items', next);
        setSelected(i + direction);
        form.clearErrors();
    }
    function submit(event: React.FormEvent) {
        event.preventDefault();
        setSaving(true);
        const options = {
            onFinish: () => setSaving(false),
            onError: (fields: Record<string, string>) => {
                const itemField = Object.keys(fields).find((key) =>
                    key.startsWith('items.'),
                );

                if (itemField) {
                    setSelected(Number(itemField.split('.')[1]));
                }
            },
        };

        if (quotation) {
            form.put(update.url(quotation.id), options);
        } else {
            form.post(store.url(), options);
        }
    }
    const hasConstruction = form.data.items.some(
        (item) =>
            item.mode === 'master' &&
            catalog.find((p) => p.id === item.catalog_id)?.construction,
    );

    return (
        <>
            <Head title={quotation ? '見積書を編集' : '見積書を作成'} />
            <form
                onSubmit={submit}
                className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8"
            >
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="mb-1 text-sm text-muted-foreground">
                            {quotation?.number ?? '新しい見積書'}
                        </p>
                        <h1 className="text-2xl font-bold">
                            {quotation ? '見積書を編集' : '見積書を作成'}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            品目に合わせて条件を設定し、見積書と内訳書を作成します。
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            一覧へ
                        </Link>
                    </Button>
                </header>
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <div className="min-w-0 space-y-6">
                        <section className="space-y-5 rounded-xl border bg-card p-4 sm:p-5">
                            <h2 className="flex items-center gap-2 font-semibold">
                                <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-xs text-primary">
                                    1
                                </span>
                                基本情報
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="得意先"
                                    required
                                    error={errors.client_name}
                                >
                                    <Input
                                        value={form.data.client_name ?? ''}
                                        maxLength={120}
                                        placeholder="会社名（御中は自動付与）"
                                        onChange={(e) =>
                                            form.setData(
                                                'client_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="工事名"
                                    required
                                    error={errors.project_name}
                                >
                                    <Input
                                        value={form.data.project_name ?? ''}
                                        maxLength={120}
                                        onChange={(e) =>
                                            form.setData(
                                                'project_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="引渡場所"
                                    error={errors.delivery_location}
                                >
                                    <Input
                                        value={
                                            form.data.delivery_location ?? ''
                                        }
                                        maxLength={200}
                                        placeholder="省略時は工事名を使用"
                                        onChange={(e) =>
                                            form.setData(
                                                'delivery_location',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField label="見積分類">
                                    <select
                                        className={quotationSelectClass}
                                        value={form.data.category}
                                        onChange={(e) =>
                                            form.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {categories.map((category) => (
                                            <option key={category}>
                                                {category}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField
                                    label="担当者"
                                    required
                                    error={errors.person_in_charge}
                                >
                                    <Input
                                        value={form.data.person_in_charge ?? ''}
                                        maxLength={80}
                                        onChange={(e) =>
                                            form.setData(
                                                'person_in_charge',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="作成日"
                                    required
                                    error={errors.issued_on}
                                >
                                    <Input
                                        type="date"
                                        value={form.data.issued_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'issued_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        </section>
                        <section className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="flex items-center gap-2 font-semibold">
                                    <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-xs text-primary">
                                        2
                                    </span>
                                    見積項目{' '}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {form.data.items.length} / 30
                                    </span>
                                </h2>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addItem}
                                    disabled={form.data.items.length >= 30}
                                >
                                    <Plus className="size-4" />
                                    項目を追加
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                項目を開いて編集できます。グレーの項目は、この品目では使用しません。
                            </p>
                            {form.data.items.map((item, i) => {
                                const product = catalog.find(
                                    (p) => p.id === item.catalog_id,
                                );
                                const line = calculation?.lines[i];
                                const itemErrors = Object.fromEntries(
                                    Object.entries(errors)
                                        .filter(([key]) =>
                                            key.startsWith(`items.${i}.`),
                                        )
                                        .map(([key, value]) => [
                                            key.slice(`items.${i}.`.length),
                                            value,
                                        ]),
                                );

                                return (
                                    <article
                                        key={i}
                                        className={cn(
                                            'overflow-hidden rounded-xl border bg-card',
                                            selected === i &&
                                                'border-primary/40 shadow-sm',
                                        )}
                                    >
                                        <div className="flex items-center gap-1 p-2 sm:gap-2 sm:p-3">
                                            <button
                                                type="button"
                                                className="flex min-w-0 flex-1 items-center gap-3 rounded-md p-2 text-left hover:bg-accent"
                                                aria-expanded={selected === i}
                                                aria-controls={`quotation-item-${i}`}
                                                onClick={() =>
                                                    setSelected(
                                                        selected === i ? -1 : i,
                                                    )
                                                }
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-7 shrink-0 items-center justify-center rounded-md text-sm font-semibold',
                                                        Object.keys(itemErrors)
                                                            .length
                                                            ? 'bg-destructive/10 text-destructive'
                                                            : 'bg-muted',
                                                    )}
                                                >
                                                    {i + 1}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-medium">
                                                        {item.mode === 'free'
                                                            ? item.free_name ||
                                                              '自由入力'
                                                            : product
                                                              ? `${product.name} ${product.specification}`
                                                              : '品目を選択してください'}
                                                    </span>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {item.mode === 'free'
                                                            ? '材料納品・その他'
                                                            : product?.note ||
                                                              'マスター選択'}
                                                        {Object.keys(itemErrors)
                                                            .length > 0 &&
                                                            ' · 入力を確認してください'}
                                                    </span>
                                                </span>
                                                <span className="hidden shrink-0 text-right text-sm sm:block">
                                                    {line ? (
                                                        <>
                                                            <strong className="tabular-nums">
                                                                {yen(
                                                                    line.amount,
                                                                )}
                                                            </strong>
                                                            <span className="block text-xs text-muted-foreground">
                                                                {Number(
                                                                    line.quantity,
                                                                )}
                                                                {line.unit} ×{' '}
                                                                {yen(
                                                                    line.unit_price,
                                                                )}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </span>
                                                {selected === i ? (
                                                    <ChevronUp className="size-4 shrink-0" />
                                                ) : (
                                                    <ChevronDown className="size-4 shrink-0" />
                                                )}
                                            </button>
                                            <div className="flex shrink-0 flex-col sm:flex-row">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                    disabled={i === 0}
                                                    aria-label={`項目${i + 1}を上へ`}
                                                    onClick={() =>
                                                        moveItem(i, -1)
                                                    }
                                                >
                                                    <ArrowUp className="size-3.5" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                    disabled={
                                                        i ===
                                                        form.data.items.length -
                                                            1
                                                    }
                                                    aria-label={`項目${i + 1}を下へ`}
                                                    onClick={() =>
                                                        moveItem(i, 1)
                                                    }
                                                >
                                                    <ArrowDown className="size-3.5" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8 text-muted-foreground hover:text-destructive"
                                                    aria-label={`項目${i + 1}を削除`}
                                                    onClick={() =>
                                                        removeItem(i)
                                                    }
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </div>
                                        </div>
                                        {selected === i && (
                                            <div id={`quotation-item-${i}`}>
                                                <ItemEditor
                                                    item={item}
                                                    catalog={catalog}
                                                    conditions={conditions}
                                                    errors={itemErrors}
                                                    line={line}
                                                    onChange={(next) =>
                                                        changeItem(i, next)
                                                    }
                                                />
                                            </div>
                                        )}
                                    </article>
                                );
                            })}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full border-dashed"
                                onClick={addItem}
                                disabled={form.data.items.length >= 30}
                            >
                                <Plus className="size-4" />
                                項目を追加
                            </Button>
                        </section>
                        <section className="space-y-5 rounded-xl border bg-card p-4 sm:p-5">
                            <h2 className="flex items-center gap-2 font-semibold">
                                <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-xs text-primary">
                                    3
                                </span>
                                諸経費・出力設定
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="交通費・諸経費（円）"
                                    error={errors.expenses}
                                >
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.expenses}
                                        onChange={(e) =>
                                            form.setData(
                                                'expenses',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="最低施工費（円）"
                                    error={errors.minimum_charge}
                                >
                                    <Input
                                        type="number"
                                        min="0"
                                        step="1"
                                        value={form.data.minimum_charge}
                                        onChange={(e) =>
                                            form.setData(
                                                'minimum_charge',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label
                                    className={cn(
                                        'flex items-start gap-3 rounded-lg border p-4 text-sm',
                                        !hasConstruction &&
                                            'bg-muted/60 text-muted-foreground',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4 accent-primary"
                                        disabled={!hasConstruction}
                                        checked={
                                            hasConstruction &&
                                            form.data.small_job
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'small_job',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span className="space-y-1">
                                        <strong className="block">
                                            少量施工を適用する
                                        </strong>
                                        <span className="block text-xs text-muted-foreground">
                                            施工費の不足分だけを加算。材料納品のみの案件は対象外です。
                                        </span>
                                    </span>
                                </label>
                                <label className="flex items-start gap-3 rounded-lg border p-4 text-sm">
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4 accent-primary"
                                        checked={form.data.holiday}
                                        onChange={(e) =>
                                            form.setData(
                                                'holiday',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span className="space-y-1">
                                        <strong className="block">
                                            休日料金を加算する
                                        </strong>
                                        <span className="block text-xs text-muted-foreground">
                                            通常合計に20,000円を1回加算します。
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField label="見積書の表示">
                                    <select
                                        className={quotationSelectClass}
                                        disabled={form.data.items.length > 5}
                                        value={
                                            form.data.items.length > 5
                                                ? 'lump_sum'
                                                : form.data.display_mode
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'display_mode',
                                                e.target.value as
                                                    'detail' | 'lump_sum',
                                            )
                                        }
                                    >
                                        <option value="detail">通常明細</option>
                                        <option value="lump_sum">
                                            工事一式
                                        </option>
                                    </select>
                                    <span className="text-xs text-muted-foreground">
                                        6件以上は自動で工事一式に切替。内訳書には全件を表示します。
                                    </span>
                                </FormField>
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2 text-sm font-medium">
                                        <input
                                            type="checkbox"
                                            className="size-4 accent-primary"
                                            checked={form.data.net_enabled}
                                            onChange={(e) =>
                                                form.setData(
                                                    'net_enabled',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        NET参考額を表示する
                                    </label>
                                    <FormField
                                        label="NET掛け率"
                                        error={errors.net_multiplier}
                                    >
                                        <Input
                                            disabled={!form.data.net_enabled}
                                            type="number"
                                            min="0"
                                            max="10"
                                            step="0.0001"
                                            value={form.data.net_multiplier}
                                            onChange={(e) =>
                                                form.setData(
                                                    'net_multiplier',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <p className="text-xs text-muted-foreground">
                                        参考表示です。通常合計・内訳の金額は変わりません。
                                    </p>
                                </div>
                            </div>
                            <FormField
                                label="見積全体の備考"
                                error={errors.notes}
                            >
                                <Textarea
                                    rows={3}
                                    maxLength={1500}
                                    value={form.data.notes ?? ''}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                />
                            </FormField>
                        </section>
                    </div>
                    <aside className="space-y-4 xl:sticky xl:top-6">
                        <div className="space-y-5 rounded-xl border bg-card p-5 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h2 className="font-semibold">見積金額</h2>
                                <span className="rounded bg-muted px-2 py-1 text-xs">
                                    税別
                                </span>
                            </div>
                            <div aria-live="polite">
                                <p className="text-3xl font-bold tracking-tight tabular-nums">
                                    {calculation ? yen(calculation.total) : '—'}
                                </p>
                                <p className="mt-2 flex min-h-8 items-center gap-1.5 text-xs text-muted-foreground">
                                    {waiting ? (
                                        <>
                                            <Loader2 className="size-3 animate-spin" />
                                            計算中…
                                        </>
                                    ) : current?.calculation ? (
                                        <>
                                            <Check className="size-3 text-emerald-600" />
                                            入力内容から計算済み
                                        </>
                                    ) : calculation ? (
                                        '入力を確認してください（前回の計算結果）'
                                    ) : (
                                        '項目の入力を確認してください'
                                    )}
                                </p>
                                {calculation?.rounding_policy ===
                                    'line_half_up_yen' && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        明細金額・諸経費は1円未満を四捨五入しています。
                                    </p>
                                )}
                            </div>
                            <dl className="space-y-3 text-sm">
                                {[
                                    ['項目合計', calculation?.subtotal],
                                    [
                                        '少量施工加算',
                                        calculation?.small_job_charge,
                                    ],
                                    ['休日加算', calculation?.holiday_charge],
                                    ['交通費・諸経費', calculation?.expenses],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between gap-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {value === undefined
                                                ? '—'
                                                : yen(value)}
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex justify-between border-t pt-3 text-xs">
                                    <dt className="text-muted-foreground">
                                        うち施工小計
                                    </dt>
                                    <dd>
                                        {calculation
                                            ? yen(
                                                  calculation.construction_subtotal,
                                              )
                                            : '—'}
                                    </dd>
                                </div>
                                {calculation && calculation.net !== null && (
                                    <div className="flex justify-between rounded-lg bg-muted p-3">
                                        <dt>NET参考額</dt>
                                        <dd className="font-semibold">
                                            {yen(calculation.net)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                            <div className="min-h-20">
                                {calculation?.warnings.map((warning) => (
                                    <p
                                        key={warning}
                                        className="rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                                    >
                                        {warning}
                                    </p>
                                ))}
                            </div>
                            {current?.message && (
                                <div
                                    role="alert"
                                    className="space-y-2 text-sm text-destructive"
                                >
                                    <p>{current.message}</p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setRetry(retry + 1)}
                                    >
                                        再試行
                                    </Button>
                                </div>
                            )}
                            {Object.keys(errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="space-y-1 text-xs text-destructive"
                                >
                                    {Object.entries(errors)
                                        .slice(0, 5)
                                        .map(([key, message]) => (
                                            <p key={key}>
                                                {key.startsWith('items.')
                                                    ? `項目${Number(key.split('.')[1]) + 1}：`
                                                    : ''}
                                                {message}
                                            </p>
                                        ))}
                                </div>
                            )}
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={form.processing || !canIssue}
                            >
                                <Save className="size-4" />
                                {form.processing ? '保存中…' : '見積を発行'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                disabled={form.processing || !canIssue}
                                onClick={() =>
                                    setPdfInput(structuredClone(form.data))
                                }
                            >
                                <FileText className="size-4" />
                                PDFを確認
                            </Button>
                            <p className="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                                <FileText className="mt-0.5 size-4 shrink-0" />
                                必要事項を入力するとPDFを確認できます。「見積を発行」で保存し、一覧からダウンロードできます。
                            </p>
                        </div>
                    </aside>
                </div>
            </form>
            {pdfInput && (
                <PdfPreview
                    input={pdfInput}
                    quotationId={quotation?.id}
                    onClose={() => setPdfInput(null)}
                />
            )}
        </>
    );
}
QuotationForm.layout = { breadcrumbs: [{ title: '見積書', href: index() }] };
