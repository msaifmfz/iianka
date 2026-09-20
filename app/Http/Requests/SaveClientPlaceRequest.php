<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Http\Requests\Concerns\NormalizesRequestInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * Adding and editing a client's place (a pin on the CRM map) share fields.
 */
class SaveClientPlaceRequest extends FormRequest
{
    use NormalizesRequestInput;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'address' => $this->nullableStringInput('address'),
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
            'kind' => ['required', Rule::enum(ClientPlaceKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'kind' => '種別',
            'name' => '地点名',
            'address' => '住所',
            'lat' => '緯度',
            'lng' => '経度',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'lat.required' => '地図上で位置を指定してください。',
            'lng.required' => '地図上で位置を指定してください。',
        ];
    }
}
