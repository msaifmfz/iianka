import type { Conditions, QuotationItem } from '@/types/quotation';

const formatter = new Intl.NumberFormat('ja-JP', { maximumFractionDigits: 5 });
export const yen = (value: string | number) =>
    `¥${formatter.format(Number(value))}`;
export const quotationSelectClass =
    'h-10 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export function blankQuotationItem(conditions: Conditions): QuotationItem {
    return {
        mode: 'master',
        catalog_id: '',
        quantity: '1',
        free_name: '',
        free_specification: '',
        free_unit: '式',
        free_price: '',
        adjustment: 'standard',
        adjustment_value: '',
        conditions: Object.fromEntries(
            Object.keys(conditions).map((key) => [key, false]),
        ) as QuotationItem['conditions'],
        rates: Object.fromEntries(
            Object.entries(conditions).map(([key, value]) => [
                key,
                String(value.rate),
            ]),
        ) as QuotationItem['rates'],
        other_rate: '0',
        length: '',
        processing_mode: 'auto',
        processing_fee: '',
        note: '',
    };
}
