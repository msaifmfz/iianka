<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesRequestInput
{
    /**
     * Trim a string input, returning null for missing, non-string, or empty values.
     */
    private function nullableStringInput(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Cast a numeric input to int, returning null for missing/empty/non-numeric values.
     */
    private function nullableIntegerInput(string $key): ?int
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
