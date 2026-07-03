<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRequestInput;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseAttachment;
use App\ReceptionCaseAttachmentKind;
use App\ReceptionCaseAttachmentSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Override;

class StoreReceptionCaseAttachmentRequest extends FormRequest
{
    use NormalizesRequestInput;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $case = $this->route('reception_case');

        return $case instanceof ReceptionCase
            && $this->user()?->can('attachFiles', $case) === true;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $duration = $this->input('duration_seconds');

        $this->merge([
            'name' => $this->nullableStringInput('name'),
            'source' => $this->nullableStringInput('source') ?? ReceptionCaseAttachmentSource::Upload->value,
            'duration_seconds' => is_numeric($duration) ? (int) $duration : $duration,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                ...$this->attachmentFileRules(),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::enum(ReceptionCaseAttachmentSource::class)],
            'duration_seconds' => [
                Rule::requiredIf(fn (): bool => $this->input('source') === ReceptionCaseAttachmentSource::Recording->value),
                'nullable',
                'integer',
                'min:1',
                'max:'.ReceptionCaseAttachment::MAX_RECORDING_SECONDS,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'file' => '添付資料',
            'name' => '表示名',
            'source' => '追加方法',
            'duration_seconds' => '録音時間',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $case = $this->route('reception_case');

                if (! $case instanceof ReceptionCase) {
                    return;
                }

                if ($case->attachments()->count() >= ReceptionCaseAttachment::MAX_ATTACHMENTS_PER_CASE) {
                    $validator->errors()->add(
                        'file',
                        '添付資料は1件につき'.ReceptionCaseAttachment::MAX_ATTACHMENTS_PER_CASE.'件までです。',
                    );
                }

                $file = $this->file('file');

                if (! $file instanceof UploadedFile) {
                    return;
                }

                $source = ReceptionCaseAttachmentSource::tryFrom((string) $this->input('source'));
                $extension = $this->extensionFor($file);
                $kind = ReceptionCaseAttachment::kindForExtension($extension);
                $mimeType = (string) $file->getMimeType();
                $isRecording = $source === ReceptionCaseAttachmentSource::Recording;

                if (! $this->mimeTypeMatchesExtension($extension, $mimeType, $isRecording)) {
                    $validator->errors()->add('file', '添付資料の拡張子とファイル形式が一致しません。');
                }

                if ($source === ReceptionCaseAttachmentSource::Capture && $kind !== ReceptionCaseAttachmentKind::Image) {
                    $validator->errors()->add('file', '撮影で追加できるのは写真ファイルのみです。');
                }

                if ($source !== ReceptionCaseAttachmentSource::Recording) {
                    return;
                }

                if ($kind !== ReceptionCaseAttachmentKind::Audio) {
                    $validator->errors()->add('file', '録音で保存できるのは音声ファイルのみです。');
                }

                $recordings = $case->attachments()
                    ->where('source', ReceptionCaseAttachmentSource::Recording->value)
                    ->count();

                if ($recordings >= ReceptionCaseAttachment::MAX_RECORDINGS_PER_CASE) {
                    $validator->errors()->add(
                        'file',
                        '録音は1件につき'.ReceptionCaseAttachment::MAX_RECORDINGS_PER_CASE.'件までです。',
                    );
                }
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    private function attachmentFileRules(): array
    {
        return [
            'file',
            'max:'.ReceptionCaseAttachment::MAX_FILE_KILOBYTES,
            'mimetypes:'.implode(',', ReceptionCaseAttachment::allowedMimeTypes()),
            'extensions:'.implode(',', ReceptionCaseAttachment::allowedExtensions()),
        ];
    }

    private function extensionFor(UploadedFile $file): string
    {
        return mb_strtolower($file->getClientOriginalExtension());
    }

    private function mimeTypeMatchesExtension(string $extension, string $mimeType, bool $isRecording): bool
    {
        $allowedMimeTypes = ReceptionCaseAttachment::allowedMimeTypesForExtension($extension, $isRecording);

        return $allowedMimeTypes !== [] && in_array(mb_strtolower($mimeType), $allowedMimeTypes, true);
    }
}
