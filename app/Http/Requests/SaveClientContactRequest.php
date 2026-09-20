<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRequestInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Adding and editing a client's contact person share the same fields.
 */
class SaveClientContactRequest extends FormRequest
{
    use NormalizesRequestInput;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'title' => $this->nullableStringInput('title'),
            'phone' => $this->nullableStringInput('phone'),
            'email' => $this->nullableStringInput('email'),
            'note' => $this->nullableStringInput('note'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canManageContent() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'title' => '役職',
            'phone' => '電話番号',
            'email' => 'メールアドレス',
            'note' => 'メモ',
        ];
    }
}
