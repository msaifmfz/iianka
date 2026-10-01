<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesClientFields;
use App\Http\Requests\Concerns\ValidatesClientPlaceFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StoreMapClientRequest extends FormRequest
{
    use ValidatesClientFields {
        attributes as private clientAttributes;
    }
    use ValidatesClientPlaceFields;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareClientInput();

        $this->merge([
            'place_name' => trim((string) $this->input('place_name', '')),
            'address' => $this->nullableStringInput('address'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canManageContent() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->clientRules(), ...$this->placeRules('place_name')];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [...$this->clientAttributes(), ...$this->placeAttributes('place_name')];
    }
}
