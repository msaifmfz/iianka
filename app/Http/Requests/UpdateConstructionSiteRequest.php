<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesGuideFileUploads;
use App\Models\SiteGuideFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class UpdateConstructionSiteRequest extends FormRequest
{
    use ValidatesGuideFileUploads;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $siteGuideFile = $this->route('site_guide_file');
        $uniqueName = Rule::unique(SiteGuideFile::class, 'name');

        if ($siteGuideFile instanceof SiteGuideFile) {
            $uniqueName->ignore($siteGuideFile);
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'guide_file' => [
                'nullable',
                ...$this->guideFileRules(),
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
            'name' => '表示名',
        ];
    }
}
