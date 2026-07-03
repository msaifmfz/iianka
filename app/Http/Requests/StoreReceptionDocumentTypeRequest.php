<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ReceptionDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class StoreReceptionDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReceptionDocumentType::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(ReceptionDocumentType::class, 'name')],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'name' => '案件書類名',
            'sort_order' => '表示順',
            'is_active' => '有効状態',
        ];
    }
}
