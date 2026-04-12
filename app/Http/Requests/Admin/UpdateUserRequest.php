<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            ...$this->accountRules($user instanceof User ? $user->id : null),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_hidden_from_workers' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');

                if (! $user instanceof User || $this->user()?->is($user) !== true) {
                    return;
                }

                if ($this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', '自分自身の管理者権限は解除できません。');
                }
            },
        ];
    }
}
