<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Reception\Enums\ReceptionCasePriority;
use App\Models\ReceptionDocumentType;
use Illuminate\Validation\Rule;

trait ValidatesReceptionCaseFields
{
    use NormalizesRequestInput;
    use ProvidesReceptionFieldLabels;

    /**
     * The intake fields shared by every reception-case write (draft, submit, update).
     *
     * @var list<string>
     */
    public const CASE_FIELDS = [
        'priority',
        'company_name',
        'site_name',
        'reception_document_type_id',
        'reception_content',
        'due_on',
        'scheduled_on',
    ];

    protected function prepareReceptionCaseFieldsForValidation(): void
    {
        $this->merge([
            'company_name' => $this->nullableStringInput('company_name'),
            'site_name' => $this->nullableStringInput('site_name'),
            'reception_content' => $this->nullableStringInput('reception_content'),
            'reception_document_type_id' => $this->nullableIntegerInput('reception_document_type_id'),
            'due_on' => $this->nullableStringInput('due_on'),
            'scheduled_on' => $this->nullableStringInput('scheduled_on'),
        ]);

        // Only normalize priority when the caller sent it. An omitted priority is
        // left untouched so partial updates preserve the stored value instead of
        // silently resetting it to normal (new cases fall back to the DB default).
        if (is_string($priority = $this->input('priority'))) {
            $this->merge(['priority' => trim($priority)]);
        }
    }

    /**
     * The validated intake fields, ready to persist on a reception case.
     *
     * @return array<string, mixed>
     */
    public function receptionCaseAttributes(): array
    {
        return $this->safe()->only(self::CASE_FIELDS);
    }

    /**
     * @param  'nullable'|'required'  $presence
     * @return array<string, array<int, mixed>>
     */
    protected function receptionCaseFieldRules(string $presence = 'nullable'): array
    {
        return [
            'priority' => ['sometimes', 'required', Rule::enum(ReceptionCasePriority::class)],
            'company_name' => [$presence, 'string', 'max:255'],
            'site_name' => [$presence, 'string', 'max:255'],
            'reception_document_type_id' => [$presence, 'integer', Rule::exists(ReceptionDocumentType::class, 'id')],
            'reception_content' => [$presence, 'string'],
            'due_on' => [$presence, 'date'],
            'scheduled_on' => ['nullable', 'date'],
        ];
    }
}
