<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesReceptionCaseFields;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;

class StoreReceptionDraftRequest extends FormRequest
{
    use ValidatesReceptionCaseFields;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareReceptionCaseFieldsForValidation();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', ReceptionCase::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->receptionCaseFieldRules(),
            'media_only' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('media_only')) {
                    return;
                }

                $values = collect($this->only(self::CASE_FIELDS))
                    ->except('priority')
                    ->filter(fn (mixed $value): bool => filled($value));

                if ($values->isEmpty()) {
                    $validator->errors()->add('company_name', '入力後に下書きを作成します。');
                }
            },
        ];
    }
}
