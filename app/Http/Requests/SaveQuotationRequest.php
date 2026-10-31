<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Quotation\QuotationCatalog;
use App\Domain\Quotation\QuotationCalculator;
use App\Models\Quotation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveQuotationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('net_enabled')) {
            $this->merge(['net_multiplier' => '1']);
        }
        $items = $this->input('items');
        if (! is_array($items) || count($items) > 30) {
            return;
        }
        $catalog = app(QuotationCatalog::class)->indexed();
        $quotation = $this->route('quotation');
        if ($quotation instanceof Quotation) {
            $catalog = array_replace($catalog, $quotation->catalog_snapshot);
        }
        foreach ($items as &$item) {
            if (! is_array($item) || ! is_array($item['conditions'] ?? null) || ! is_array($item['rates'] ?? null)) {
                continue;
            }
            $free = ($item['mode'] ?? '') === 'free';
            $id = $item['catalog_id'] ?? '';
            $product = is_string($id) ? ($catalog[$id] ?? null) : null;
            foreach (QuotationCalculator::CONDITIONS as $key => $condition) {
                if ($free || ! ($product['conditions'] ?? false) || in_array($key, $product['included_conditions'] ?? [], true) || ! ($item['conditions'][$key] ?? false)) {
                    $item['conditions'][$key] = false;
                    $item['rates'][$key] = (string) $condition['rate'];
                }
            }
            if ($free || ! ($product['conditions'] ?? false)) {
                $item['other_rate'] = '0';
            }
            if ($free) {
                $item['catalog_id'] = null;
                $item['adjustment'] = 'standard';
            } else {
                $item['free_price'] = null;
            }
            if (($item['adjustment'] ?? '') === 'standard') {
                $item['adjustment_value'] = null;
            }
            if ($free || ! ($product['processing'] ?? false)) {
                $item['length'] = null;
            }
            $length = $item['length'] ?? null;
            if ($length === null || $length === '' || (is_numeric($length) && $product !== null && $product['stock_length'] !== null && (float) $length === (float) $product['stock_length'])) {
                $item['processing_mode'] = 'auto';
            }
            if (($item['processing_mode'] ?? '') !== 'manual') {
                $item['processing_fee'] = null;
            }
        }
        unset($item);
        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canManageContent() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $preview = $this->routeIs('quotations.preview');
        $keys = implode(',', array_keys(QuotationCalculator::CONDITIONS));

        return [
            'version' => [$this->isMethod('put') ? 'required' : 'nullable', 'integer', 'min:1'],
            'client_name' => [$preview ? 'nullable' : 'required', 'string', 'max:120'],
            'project_name' => [$preview ? 'nullable' : 'required', 'string', 'max:120'],
            'delivery_location' => ['nullable', 'string', 'max:200'],
            'person_in_charge' => [$preview ? 'nullable' : 'required', 'string', 'max:80'],
            'issued_on' => [$preview ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'category' => ['required', Rule::in(QuotationCalculator::CATEGORIES)],
            'expenses' => ['required', 'numeric', 'decimal:0,2', 'between:0,10000000'],
            'small_job' => ['required', 'boolean'],
            'minimum_charge' => ['required', 'integer', 'between:0,10000000'],
            'holiday' => ['required', 'boolean'],
            'net_enabled' => ['required', 'boolean'],
            'net_multiplier' => ['required', 'numeric', 'decimal:0,4', 'between:0,10'],
            'display_mode' => ['required', Rule::in(['detail', 'lump_sum'])],
            'notes' => ['nullable', 'string', 'max:1500'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'items.*' => ['required', 'array:mode,catalog_id,quantity,free_name,free_specification,free_unit,free_price,adjustment,adjustment_value,conditions,rates,other_rate,length,processing_mode,processing_fee,note'],
            'items.*.mode' => ['required', Rule::in(['master', 'free'])],
            'items.*.catalog_id' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:10000'],
            'items.*.free_name' => ['nullable', 'string', 'max:120'],
            'items.*.free_specification' => ['nullable', 'string', 'max:120'],
            'items.*.free_unit' => ['nullable', 'string', 'max:10'],
            'items.*.free_price' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,10000000'],
            'items.*.adjustment' => ['required', Rule::in(['standard', 'add', 'multiply', 'direct'])],
            'items.*.adjustment_value' => ['nullable', 'numeric', 'decimal:0,4', 'between:-10000000,10000000'],
            'items.*.conditions' => ['required', 'array:'.$keys],
            'items.*.conditions.*' => ['boolean'],
            'items.*.rates' => ['required', 'array:'.$keys],
            'items.*.rates.*' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000'],
            'items.*.other_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000'],
            'items.*.length' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:10000'],
            'items.*.processing_mode' => ['required', Rule::in(['auto', 'none', 'manual'])],
            'items.*.processing_fee' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,1000000'],
            'items.*.note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return list<Closure(Validator):void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $catalog = app(QuotationCatalog::class)->indexed();
            $quotation = $this->route('quotation');
            if ($quotation instanceof Quotation) {
                $catalog = array_replace($catalog, $quotation->catalog_snapshot);
            }
            foreach ($this->input('items', []) as $i => $item) {
                $prefix = 'items.'.$i.'.';
                if ($item['mode'] === 'free') {
                    foreach (['free_name' => '品名', 'free_unit' => '単位', 'free_price' => '単価'] as $field => $label) {
                        if (($item[$field] ?? null) === null || $item[$field] === '') {
                            $validator->errors()->add($prefix.$field, $label.'を入力してください。');
                        }
                    }

                    continue;
                }
                $product = $catalog[$item['catalog_id'] ?? ''] ?? null;
                if ($product === null) {
                    $validator->errors()->add($prefix.'catalog_id', '品目を選択してください。');

                    continue;
                }
                if ($item['adjustment'] !== 'standard' && ($item['adjustment_value'] ?? null) === null) {
                    $validator->errors()->add($prefix.'adjustment_value', '調整値を入力してください。');
                } elseif ($item['adjustment'] === 'multiply' && (float) $item['adjustment_value'] > 100) {
                    $validator->errors()->add($prefix.'adjustment_value', '倍率は100以下にしてください。');
                } elseif (in_array($item['adjustment'], ['direct', 'multiply'], true) && (float) $item['adjustment_value'] < 0
                    || $item['adjustment'] === 'add' && (float) $product['price'] + (float) $item['adjustment_value'] < 0) {
                    $validator->errors()->add($prefix.'adjustment_value', '調整後の単価は0円以上にしてください。');
                }
                $length = $item['length'] ?? null;
                if ($product['processing'] && $length !== null) {
                    if ($product['stock_length'] !== null && (float) $length > $product['stock_length']) {
                        $validator->errors()->add($prefix.'length', '施工寸法は仕入規格寸法以下にしてください。');
                    }
                    if ($item['processing_mode'] === 'manual' && ($item['processing_fee'] ?? null) === null) {
                        $validator->errors()->add($prefix.'processing_fee', '手動加工代を入力してください。');
                    }
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'version' => '更新バージョン', 'client_name' => '得意先', 'project_name' => '工事名',
            'delivery_location' => '引渡場所', 'person_in_charge' => '担当者', 'issued_on' => '作成日',
            'category' => '見積分類', 'expenses' => '交通費・諸経費', 'small_job' => '少量施工',
            'minimum_charge' => '最低施工費', 'holiday' => '休日料金', 'net_enabled' => 'NET参考額の表示',
            'net_multiplier' => 'NET掛け率', 'display_mode' => '見積書の表示', 'notes' => '見積全体の備考',
            'items' => '明細', 'items.*' => '見積項目', 'items.*.mode' => '入力方法',
            'items.*.catalog_id' => '品目', 'items.*.quantity' => '数量', 'items.*.free_name' => '品名',
            'items.*.free_specification' => '規格', 'items.*.free_unit' => '単位', 'items.*.free_price' => '自由単価',
            'items.*.adjustment' => '単価調整', 'items.*.adjustment_value' => '調整値',
            'items.*.conditions' => '施工条件', 'items.*.conditions.*' => '施工条件',
            'items.*.rates' => '割増率', 'items.*.rates.*' => '割増率', 'items.*.other_rate' => 'その他割増',
            'items.*.length' => '施工寸法', 'items.*.processing_mode' => '加工代の設定',
            'items.*.processing_fee' => '加工代', 'items.*.note' => '備考（お客様に表示）',
        ];
    }
}
