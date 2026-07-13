<?php

namespace App\Http\Requests;

use App\Models\ImportSetting;
use App\Services\Import\ArchiveValidator;
use App\Services\Import\ContentSanitizer;
use App\Services\ProjectImporter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of an uploaded import archive.
 *
 * Only field-level upload validation lives here (is it a file, a zip, within the
 * size cap). Deeper STRUCTURAL validation — the zip's arborescence, per-file JSON
 * shape, content-sniffed media, allow-listed HTML — is deferred to the service
 * layer ({@see ArchiveValidator} /
 * {@see ContentSanitizer}, run by
 * {@see ProjectImporter::start()}), because Laravel's rule engine
 * cannot express "this file inside this zip must be valid Markdown."
 */
class ImportProjectRequest extends FormRequest
{
    /**
     * Import creates a BRAND-NEW project for the current user — there is no
     * existing Project to walk up to (ProjectPolicy), so this uses the same
     * any-authenticated-user exception CrawlerSetting/GeneralSettings do: the
     * `auth` + `access-admin` middleware already blocks guests; this simply
     * confirms a user is present. See documentation/architecture.md.
     */
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
            // The size cap is read LIVE from the admin-tunable ImportSetting
            // singleton (never a hard-coded number), so raising/lowering it in
            // the UI takes effect immediately with no deploy. Laravel's `max`
            // for files is in kilobytes, matching the column's unit.
            'archive' => [
                'required',
                'file',
                'mimes:zip',
                'max:'.ImportSetting::current()->max_archive_kilobytes,
            ],
        ];
    }
}
