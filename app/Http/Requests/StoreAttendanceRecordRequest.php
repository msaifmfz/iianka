<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class StoreAttendanceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'status' => ['required', Rule::in([
                AttendanceRecord::STATUS_WORKING,
                AttendanceRecord::STATUS_LEAVE,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if ($this->has('note')) {
            $note = trim((string) $this->input('note'));

            $this->merge([
                'note' => $note === '' ? null : $note,
            ]);
        }
    }
}
