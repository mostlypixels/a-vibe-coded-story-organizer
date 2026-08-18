<?php

namespace App\Http\Requests;

use App\Models\ImportSetting;
use App\Services\ProjectImporter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the uploaded ZIP and its size.
 *
 * {@see ProjectImporter} validates files and content inside the archive.
 */
class ImportProjectRequest extends FormRequest
{
    /** Import has no project to authorize until it creates one. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Laravel and ImportSetting both measure file limits in kilobytes.
            'archive' => [
                'required',
                'file',
                'mimes:zip',
                'max:'.ImportSetting::current()->max_archive_kilobytes,
            ],
        ];
    }
}
