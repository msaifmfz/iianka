<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Crm\AttachmentTypeGuard;
use App\Http\Requests\Concerns\NormalizesRequestInput;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Override;

/**
 * One document filed on a client. Any signed-in user may add one, so field
 * staff can file a signed contract on the spot.
 */
class StoreClientDocumentRequest extends FormRequest
{
    use NormalizesRequestInput;

    private const string UNSUPPORTED_FILE_MESSAGE = '登録できるのは書類（PDF・Word・Excel・PowerPoint・テキスト・CSV）と写真のみです。';

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->nullableStringInput('name'),
            'issued_on' => $this->nullableStringInput('issued_on'),
            'client_place_id' => $this->input('client_place_id') === '' ? null : $this->input('client_place_id'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
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
                'file',
                'max:'.ClientDocument::MAX_FILE_KILOBYTES,
                'extensions:'.implode(',', ClientDocument::allowedExtensions()),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'client_place_id' => [
                'nullable',
                'integer',
                Rule::exists('client_places', 'id')->where('client_id', $this->client()->id),
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
            'file' => 'ファイル',
            'name' => '書類名',
            'issued_on' => '日付',
            'client_place_id' => '関連する地点',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $maxMegabytes = intdiv(ClientDocument::MAX_FILE_KILOBYTES, 1024);

        return [
            'file.max' => "ファイルは{$maxMegabytes}MBまでです。",
            'file.extensions' => self::UNSUPPORTED_FILE_MESSAGE,
        ];
    }

    /**
     * Reject files whose real content does not match their extension:
     * images and PDFs are served inline.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($file instanceof UploadedFile && ! $validator->errors()->has('file') && ! (new AttachmentTypeGuard)->accepts($file)) {
                    $validator->errors()->add('file', self::UNSUPPORTED_FILE_MESSAGE);
                }
            },
        ];
    }

    public function client(): Client
    {
        $client = $this->route('client');

        abort_unless($client instanceof Client, 404);

        return $client;
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        return $file;
    }

    /**
     * @return array{name: string|null, issued_on: string|null, client_place_id: int|null}
     */
    public function documentFields(): array
    {
        $name = $this->validated('name');
        $issuedOn = $this->validated('issued_on');
        $placeId = $this->validated('client_place_id');

        return [
            'name' => is_string($name) ? $name : null,
            'issued_on' => is_string($issuedOn) ? $issuedOn : null,
            'client_place_id' => is_numeric($placeId) ? (int) $placeId : null,
        ];
    }
}
