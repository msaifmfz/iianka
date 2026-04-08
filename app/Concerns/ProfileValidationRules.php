<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate managed user accounts.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function accountRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'login_id' => $this->loginIdRules($userId),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user login IDs.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function loginIdRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            'alpha_dash',
            'lowercase',
            $this->uniqueRule('login_id', $userId),
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        $rules = [
            'nullable',
            'string',
            'email',
            'max:255',
        ];

        $rules[] = $this->uniqueRule('email', $userId)->where(
            fn ($query) => $query->whereNotNull('email')
        );

        return $rules;
    }

    private function uniqueRule(string $column, ?int $userId = null): Unique
    {
        return $userId === null
            ? Rule::unique(User::class, $column)
            : Rule::unique(User::class, $column)->ignore($userId);
    }
}
