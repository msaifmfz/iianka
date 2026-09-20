<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared normalization and rules for creating and editing a CRM client.
 */
trait ValidatesClientFields
{
    use NormalizesRequestInput;

    protected function prepareClientInput(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'short_label' => trim((string) $this->input('short_label', '')),
            'color' => strtolower(trim((string) $this->input('color', ''))),
            'note' => $this->nullableStringInput('note'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function clientRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_label' => ['required', 'string', 'max:3'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '顧客名',
            'short_label' => '略称',
            'color' => '色',
            'note' => 'メモ',
        ];
    }
}
