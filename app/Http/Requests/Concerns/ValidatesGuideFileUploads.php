<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

trait ValidatesGuideFileUploads
{
    /**
     * @var array<int, string>
     */
    private const array GUIDE_FILE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    /**
     * @return array<int, File|string>
     */
    protected function guideFileRules(): array
    {
        return [
            File::types(self::GUIDE_FILE_EXTENSIONS)->max(50 * 1024),
            'extensions:'.implode(',', self::GUIDE_FILE_EXTENSIONS),
        ];
    }

    /**
     * @return array<int, callable>
     */
    protected function guideFileUploadAfterValidation(): array
    {
        return [
            function (Validator $validator): void {
                $files = $this->file('guide_files', []);
                $names = $this->input('guide_file_names', []);

                if (! is_array($files)) {
                    return;
                }

                foreach ($files as $index => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $name = is_array($names) ? ($names[$index] ?? null) : null;

                    if (! is_string($name) || trim($name) === '') {
                        $validator->errors()->add("guide_file_names.{$index}", '表示名を入力してください。');
                    }
                }
            },
        ];
    }
}
