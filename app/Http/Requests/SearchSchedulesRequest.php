<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class SearchSchedulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location' => ['nullable', 'string', 'max:255'],
            'general_contractor' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'selected_type' => ['nullable', 'string', Rule::in(['construction', 'business'])],
            'selected_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $direction = strtolower((string) $this->input('direction', 'desc'));
        $selectedType = $this->normalizedString('selected_type');

        $this->merge([
            'location' => $this->normalizedString('location'),
            'general_contractor' => $this->normalizedString('general_contractor'),
            'direction' => in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
            'selected_type' => in_array($selectedType, ['construction', 'business'], true) ? $selectedType : null,
            'selected_id' => $this->normalizedPositiveInteger('selected_id'),
        ]);
    }

    public function locationFilter(): ?string
    {
        return $this->validatedString('location');
    }

    public function generalContractorFilter(): ?string
    {
        return $this->validatedString('general_contractor');
    }

    public function direction(): string
    {
        $direction = $this->validatedString('direction');

        return $direction ?? 'desc';
    }

    public function selectedType(): ?string
    {
        return $this->validatedString('selected_type');
    }

    public function selectedId(): ?int
    {
        $selectedId = $this->validated('selected_id');

        return is_numeric($selectedId) ? (int) $selectedId : null;
    }

    private function normalizedString(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function validatedString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function normalizedPositiveInteger(string $key): ?int
    {
        $value = $this->input($key);

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}
