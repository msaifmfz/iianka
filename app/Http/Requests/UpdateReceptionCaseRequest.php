<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Http\Requests\Concerns\ValidatesReceptionCaseFields;
use App\Models\ReceptionCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * @property-read ReceptionCase $reception_case
 */
class UpdateReceptionCaseRequest extends FormRequest
{
    use ValidatesReceptionCaseFields;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->prepareReceptionCaseFieldsForValidation();
    }

    public function authorize(): bool
    {
        if ($this->user()?->can('update', $this->reception_case) !== true) {
            return false;
        }

        if (! $this->changesPriority()) {
            return true;
        }

        return $this->user()->can('updatePriority', $this->reception_case);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Drafts stay leniently editable; once submitted, the required intake
        // fields must remain present so they cannot be silently blanked.
        $presence = $this->reception_case->status === ReceptionCaseStatus::Draft
            ? 'nullable'
            : 'required';

        return $this->receptionCaseFieldRules($presence);
    }

    private function changesPriority(): bool
    {
        if (! $this->exists('priority')) {
            return false;
        }

        $priority = $this->input('priority');

        if (! is_string($priority)) {
            return true;
        }

        return trim($priority) !== $this->reception_case->priority->value;
    }
}
