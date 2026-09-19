<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Crm\AttachmentTypeGuard;
use App\Domain\Crm\Enums\ClientPlaceLogType;
use App\Domain\Crm\Enums\ClientReaction;
use App\Http\Requests\Concerns\NormalizesRequestInput;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Services\BusinessDate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Override;

/**
 * A CRM history entry, with optional photos and voice memos sent in the same
 * request so field staff submit a visit in one tap.
 *
 * Any signed-in user may add an entry; editing is limited to its author (or
 * an admin).
 */
class SaveClientPlaceLogRequest extends FormRequest
{
    use NormalizesRequestInput;

    /** How far ahead of the server an entry may be dated, to absorb device clock skew. */
    private const int FUTURE_GRACE_MINUTES = 5;

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'summary' => trim((string) $this->input('summary', '')),
            'reaction' => $this->nullableStringInput('reaction'),
            'client_contact_id' => $this->input('client_contact_id') === '' ? null : $this->input('client_contact_id'),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $log = $this->route('client_place_log');

        return ! $log instanceof ClientPlaceLog || $log->isEditableBy($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ClientPlaceLogType::class)],
            // The value is wall-clock business time typed on the user's own
            // device, so allow a few minutes of clock skew rather than
            // blocking a submission the phone believes is "now".
            'occurred_at' => [
                'required',
                'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s',
                'before_or_equal:'.now(BusinessDate::TIMEZONE)->addMinutes(self::FUTURE_GRACE_MINUTES)->toDateTimeString(),
            ],
            'summary' => ['required', 'string', 'max:5000'],
            'reaction' => ['nullable', Rule::enum(ClientReaction::class)],
            'client_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('client_contacts', 'id')->where('client_id', $this->place()->client_id),
            ],
            'attachments' => ['nullable', 'array', 'max:'.ClientPlaceLogAttachment::MAX_PER_LOG],
            'attachments.*.file' => [
                'required',
                'file',
                'max:'.ClientPlaceLogAttachment::MAX_FILE_KILOBYTES,
                'extensions:'.implode(',', [...ClientPlaceLogAttachment::IMAGE_EXTENSIONS, ...ClientPlaceLogAttachment::AUDIO_EXTENSIONS]),
            ],
            'attachments.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:'.ClientPlaceLogAttachment::MAX_RECORDING_SECONDS],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'type' => '種類',
            'occurred_at' => '日時',
            'summary' => '内容',
            'reaction' => '反応',
            'client_contact_id' => '対応した担当者',
            'attachments' => '添付',
            'attachments.*.file' => '添付ファイル',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $maxMegabytes = intdiv(ClientPlaceLogAttachment::MAX_FILE_KILOBYTES, 1024);

        return [
            'attachments.*.file.max' => "添付ファイルは{$maxMegabytes}MBまでです。",
            'attachments.*.file.extensions' => '添付できるのは写真と音声のみです。',
            'occurred_at.before_or_equal' => '日時に未来の日付は指定できません。',
        ];
    }

    /**
     * Reject files whose real content is not an image or audio, whatever
     * their extension says: attachments are served inline.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        $guard = new AttachmentTypeGuard;

        return [
            function (Validator $validator) use ($guard): void {
                $attachments = data_get($this->allFiles(), 'attachments');

                if (! is_array($attachments)) {
                    return;
                }

                $log = $this->route('client_place_log');
                $existingCount = $log instanceof ClientPlaceLog ? $log->attachments()->count() : 0;

                if ($existingCount + count($attachments) > ClientPlaceLogAttachment::MAX_PER_LOG) {
                    $validator->errors()->add('attachments', '添付は1件の記録につき'.ClientPlaceLogAttachment::MAX_PER_LOG.'件までです。');
                }

                foreach ($attachments as $index => $attachment) {
                    $file = data_get($attachment, 'file');
                    $key = "attachments.{$index}.file";

                    if (! $file instanceof UploadedFile || $validator->errors()->has($key)) {
                        continue;
                    }

                    if (! $guard->accepts($file)) {
                        $validator->errors()->add($key, '添付できるのは写真と音声のみです。');
                    }
                }
            },
        ];
    }

    /**
     * The place the entry belongs to: from the route on create, or from the
     * entry being edited.
     */
    public function place(): ClientPlace
    {
        $place = $this->route('client_place');

        if ($place instanceof ClientPlace) {
            return $place;
        }

        $log = $this->route('client_place_log');

        abort_unless($log instanceof ClientPlaceLog, 404);

        return $log->place;
    }

    /**
     * `occurred_at` arrives as wall-clock business time from a
     * datetime-local input and is stored in UTC like every other timestamp.
     *
     * @return array{type: string, occurred_at: string, summary: string, reaction: string|null, client_contact_id: int|null}
     */
    public function logFields(): array
    {
        $reaction = $this->validated('reaction');
        $contactId = $this->validated('client_contact_id');

        return [
            'type' => (string) $this->validated('type'),
            'occurred_at' => Date::parse((string) $this->validated('occurred_at'), BusinessDate::TIMEZONE)->utc()->toDateTimeString(),
            'summary' => (string) $this->validated('summary'),
            'reaction' => is_string($reaction) ? $reaction : null,
            'client_contact_id' => is_numeric($contactId) ? (int) $contactId : null,
        ];
    }

    /**
     * @return list<array{file: UploadedFile, duration_seconds: int|null}>
     */
    public function attachmentUploads(): array
    {
        $uploads = [];
        $durations = $this->input('attachments', []);
        $files = data_get($this->allFiles(), 'attachments');

        foreach (is_array($files) ? $files : [] as $index => $attachment) {
            $file = data_get($attachment, 'file');

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $duration = is_array($durations) ? data_get($durations, "{$index}.duration_seconds") : null;

            $uploads[] = [
                'file' => $file,
                'duration_seconds' => is_numeric($duration) ? (int) $duration : null,
            ];
        }

        return $uploads;
    }
}
