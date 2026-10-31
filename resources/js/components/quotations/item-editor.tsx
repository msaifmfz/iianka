import FormField from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { blankQuotationItem, quotationSelectClass, yen } from '@/lib/quotation';
import { cn } from '@/lib/utils';
import type {
    CatalogItem,
    ConditionKey,
    Conditions,
    QuotationItem,
    QuotationLine,
} from '@/types/quotation';
import CatalogPicker from './catalog-picker';

type Props = {
    item: QuotationItem;
    catalog: CatalogItem[];
    conditions: Conditions;
    onChange: (item: QuotationItem) => void;
    errors: Record<string, string>;
    line?: QuotationLine;
};

export default function ItemEditor({
    item,
    catalog,
    conditions,
    onChange,
    errors,
    line,
}: Props) {
    const product =
        item.mode === 'master'
            ? catalog.find((p) => p.id === item.catalog_id)
            : undefined;
    const free = item.mode === 'free';
    const canPrice = Boolean(product) && !free;
    const canConditions = Boolean(product?.conditions) && !free;
    const canProcess = Boolean(product?.processing) && !free;
    const processingNeeded =
        canProcess &&
        item.length !== '' &&
        (product?.stock_length === null ||
            Number(item.length) !== product?.stock_length);
    const set = <K extends keyof QuotationItem>(
        key: K,
        value: QuotationItem[K],
    ) => onChange({ ...item, [key]: value });
    const inactiveReason = free
        ? '自由入力では施工割増・加工代を自動計算しません。単価に含めて入力してください。'
        : !product
          ? '品目を選択すると、使用できる項目が有効になります。'
          : 'この品目には適用されません。';

    return (
        <div className="space-y-6 border-t p-4 sm:p-5">
            <div className="flex flex-wrap items-end gap-3">
                <FormField label="入力方法" className="w-40">
                    <select
                        className={quotationSelectClass}
                        value={item.mode}
                        onChange={(e) =>
                            onChange({
                                ...blankQuotationItem(conditions),
                                mode: e.target.value as QuotationItem['mode'],
                                quantity: item.quantity,
                                note: item.note,
                            })
                        }
                    >
                        <option value="master">マスター選択</option>
                        <option value="free">自由入力</option>
                    </select>
                </FormField>
                {!free && (
                    <CatalogPicker
                        catalog={catalog}
                        label={product ? '品目を変更' : '品目を選択'}
                        onSelect={(p) =>
                            onChange({
                                ...blankQuotationItem(conditions),
                                catalog_id: p.id,
                                quantity: item.quantity,
                                note: item.note,
                            })
                        }
                    />
                )}
                {product && (
                    <p className="pb-2 text-sm text-muted-foreground">
                        標準単価{' '}
                        <strong className="text-foreground">
                            {yen(product.price)}
                        </strong>
                        ／{product.unit}
                    </p>
                )}
            </div>
            {errors.catalog_id && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.catalog_id}
                </p>
            )}
            {product && (
                <div className="rounded-lg bg-muted/50 p-3 text-sm">
                    <strong>
                        {product.name} {product.specification}
                    </strong>
                    <p className="mt-1 text-muted-foreground">
                        {product.note || '標準仕様'}
                        {product.stock_length !== null &&
                            ` · 仕入規格寸法 ${product.stock_length}mm`}
                    </p>
                </div>
            )}
            {free && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="品名" required error={errors.free_name}>
                        <Input
                            value={item.free_name ?? ''}
                            maxLength={120}
                            onChange={(e) => set('free_name', e.target.value)}
                        />
                    </FormField>
                    <FormField label="規格">
                        <Input
                            value={item.free_specification ?? ''}
                            maxLength={120}
                            onChange={(e) =>
                                set('free_specification', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField label="単位" required error={errors.free_unit}>
                        <Input
                            value={item.free_unit ?? ''}
                            maxLength={10}
                            onChange={(e) => set('free_unit', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label="単価（税別・円）"
                        required
                        error={errors.free_price}
                    >
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={item.free_price ?? ''}
                            onChange={(e) => set('free_price', e.target.value)}
                        />
                    </FormField>
                    <p className="text-xs text-muted-foreground sm:col-span-2">
                        自由入力は材料納品・その他として扱い、最低施工費の判定から除外します。
                    </p>
                </div>
            )}
            <div className="grid gap-4 sm:grid-cols-3">
                <FormField
                    label={`数量${product ? `（${product.unit}）` : ''}`}
                    required
                    error={errors.quantity}
                >
                    <Input
                        type="number"
                        min="0.001"
                        max="10000"
                        step="0.001"
                        value={item.quantity}
                        onChange={(e) => set('quantity', e.target.value)}
                    />
                </FormField>
                <FormField
                    label="単価調整"
                    className={cn(!canPrice && 'opacity-50')}
                >
                    <select
                        disabled={!canPrice}
                        className={quotationSelectClass}
                        value={item.adjustment}
                        onChange={(e) =>
                            onChange({
                                ...item,
                                adjustment: e.target
                                    .value as QuotationItem['adjustment'],
                                adjustment_value: '',
                            })
                        }
                    >
                        <option value="standard">標準単価</option>
                        <option value="add">加減算（円）</option>
                        <option value="multiply">倍率</option>
                        <option value="direct">直接指定（円）</option>
                    </select>
                </FormField>
                <FormField
                    label={
                        item.adjustment === 'multiply' ? '倍率' : '調整値（円）'
                    }
                    error={errors.adjustment_value}
                    className={cn(
                        (!canPrice || item.adjustment === 'standard') &&
                            'opacity-50',
                    )}
                >
                    <Input
                        disabled={!canPrice || item.adjustment === 'standard'}
                        type="number"
                        step="0.0001"
                        value={item.adjustment_value ?? ''}
                        placeholder={
                            item.adjustment === 'multiply'
                                ? '例：1.1'
                                : '例：100'
                        }
                        onChange={(e) =>
                            set('adjustment_value', e.target.value)
                        }
                    />
                </FormField>
            </div>
            <section className="space-y-3">
                <div>
                    <h3 className="font-semibold">施工条件</h3>
                    <p className="text-xs text-muted-foreground">
                        {canConditions
                            ? '割増率は足し合わせて計算します。現場に合わせて変更できます。'
                            : inactiveReason}
                    </p>
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                    {(
                        Object.entries(conditions) as [
                            ConditionKey,
                            Conditions[ConditionKey],
                        ][]
                    ).map(([key, condition]) => {
                        const included =
                            product?.included_conditions.includes(key) ?? false;
                        const disabled = !canConditions || included;

                        return (
                            <div
                                key={key}
                                className={cn(
                                    'rounded-lg border p-3',
                                    disabled
                                        ? 'bg-muted/60 text-muted-foreground'
                                        : item.conditions[key]
                                          ? 'border-primary/40 bg-primary/5'
                                          : '',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <label
                                        className={cn(
                                            'flex min-h-8 items-center gap-2 text-sm',
                                            disabled
                                                ? 'cursor-not-allowed'
                                                : 'cursor-pointer',
                                        )}
                                    >
                                        <input
                                            type="checkbox"
                                            className="size-4 accent-primary"
                                            disabled={disabled}
                                            checked={
                                                included ||
                                                (!disabled &&
                                                    item.conditions[key])
                                            }
                                            onChange={(e) =>
                                                set('conditions', {
                                                    ...item.conditions,
                                                    [key]: e.target.checked,
                                                })
                                            }
                                        />
                                        {condition.label}
                                    </label>
                                    <div className="flex items-center gap-1">
                                        <Input
                                            aria-label={`${condition.label}の割増率`}
                                            className="h-8 w-20 text-right"
                                            disabled={
                                                disabled ||
                                                !item.conditions[key]
                                            }
                                            type="number"
                                            min="0"
                                            max="1000"
                                            step="0.01"
                                            value={item.rates[key]}
                                            onChange={(e) =>
                                                set('rates', {
                                                    ...item.rates,
                                                    [key]: e.target.value,
                                                })
                                            }
                                        />
                                        <span className="text-xs">%</span>
                                    </div>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {included
                                        ? '単価に含まれています（追加加算なし）'
                                        : disabled
                                          ? '対象外'
                                          : condition.provisional
                                            ? '暫定率・現場ごとに確認'
                                            : key === 'high'
                                              ? '足場・ローリング・高所作業車の併用も1回のみ'
                                              : '選択時のみ加算'}
                                </p>
                                {errors[`rates.${key}`] && (
                                    <p className="text-xs text-destructive">
                                        {errors[`rates.${key}`]}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                    <FormField
                        label="その他割増（%）"
                        error={errors.other_rate}
                        className={cn(
                            'rounded-lg border p-3',
                            !canConditions &&
                                'bg-muted/60 text-muted-foreground',
                        )}
                    >
                        <Input
                            disabled={!canConditions}
                            type="number"
                            min="0"
                            max="1000"
                            step="0.01"
                            value={item.other_rate}
                            onChange={(e) => set('other_rate', e.target.value)}
                        />
                    </FormField>
                </div>
            </section>
            <section
                className={cn(
                    'space-y-3 rounded-lg border p-4',
                    !canProcess && 'bg-muted/60 text-muted-foreground',
                )}
            >
                <div>
                    <h3 className="font-semibold">寸法・加工</h3>
                    <p className="text-xs text-muted-foreground">
                        {canProcess
                            ? '指定寸法への加工が必要な場合だけ、加工代を加算します。空欄は規格品です。'
                            : inactiveReason}
                    </p>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField label="施工寸法（mm）" error={errors.length}>
                        <Input
                            disabled={!canProcess}
                            type="number"
                            min="0.01"
                            max={product?.stock_length ?? 10000}
                            step="0.01"
                            placeholder={
                                product?.stock_length
                                    ? `規格：${product.stock_length}`
                                    : '規格品は空欄'
                            }
                            value={item.length ?? ''}
                            onChange={(e) => set('length', e.target.value)}
                        />
                    </FormField>
                    <FormField label="加工代の設定">
                        <select
                            disabled={!processingNeeded}
                            className={quotationSelectClass}
                            value={item.processing_mode}
                            onChange={(e) =>
                                set(
                                    'processing_mode',
                                    e.target
                                        .value as QuotationItem['processing_mode'],
                                )
                            }
                        >
                            <option value="auto">
                                自動
                                {product
                                    ? `（${yen(product.processing_fee)}／本）`
                                    : ''}
                            </option>
                            <option value="none">加算なし</option>
                            <option value="manual">手動指定</option>
                        </select>
                    </FormField>
                    <FormField
                        label="加工代（円／本）"
                        error={errors.processing_fee}
                    >
                        <Input
                            disabled={
                                !processingNeeded ||
                                item.processing_mode !== 'manual'
                            }
                            type="number"
                            min="0"
                            step="0.01"
                            value={item.processing_fee ?? ''}
                            onChange={(e) =>
                                set('processing_fee', e.target.value)
                            }
                        />
                    </FormField>
                </div>
            </section>
            <FormField label="備考（お客様に表示）" error={errors.note}>
                <Textarea
                    rows={2}
                    maxLength={300}
                    value={item.note ?? ''}
                    onChange={(e) => set('note', e.target.value)}
                />
            </FormField>
            <div className="flex min-h-24 flex-wrap items-center justify-between gap-3 rounded-lg bg-primary/5 p-4 text-sm">
                <p>
                    調整後 {line ? yen(line.base_price) : '—'} ＋ 割増{' '}
                    {line ? Number(line.surcharge_rate) : '—'}% ＋ 加工{' '}
                    {line ? yen(line.processing_fee) : '—'}
                    <span className="mt-1 block text-xs text-muted-foreground">
                        {free
                            ? '自由単価は丸めずに計算'
                            : '割増後に10円単位で四捨五入し、加工代を加算'}
                    </span>
                </p>
                <p className="text-right">
                    見積単価{' '}
                    <strong>{line ? yen(line.unit_price) : '—'}</strong>
                    <span className="mt-1 block text-lg font-bold">
                        {line ? yen(line.amount) : '—'}
                    </span>
                </p>
            </div>
        </div>
    );
}
