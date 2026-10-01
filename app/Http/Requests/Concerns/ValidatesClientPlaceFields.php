<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Crm\Enums\ClientPlaceKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesClientPlaceFields
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function placeRules(string $nameField = 'name'): array
    {
        return [
            'kind' => ['required', Rule::enum(ClientPlaceKind::class)],
            $nameField => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function placeAttributes(string $nameField = 'name'): array
    {
        return [
            'kind' => '種別',
            $nameField => '地点名',
            'address' => '住所',
            'lat' => '緯度',
            'lng' => '経度',
        ];
    }
}
