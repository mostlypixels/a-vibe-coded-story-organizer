<?php

namespace App\Http\Requests;

use App\Models\ImportSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an update to the global {@see ImportSetting} singleton.
 *
 * The size limit is edited in MEGABYTES (friendlier for a human) but stored in
 * KILOBYTES (the column's unit, and what Laravel's file `max` rule expects), so
 * this request also owns the MB → KB conversion via {@see self::settings()} —
 * the controller just persists what it returns.
 *
 * Like {@see UpdateCrawlerSettingRequest}, the import settings are GLOBAL (owned
 * by no Project or User), so this uses the any-authenticated-user exception: the
 * `auth` + `access-admin` middleware blocks guests; this confirms a user is
 * present. See documentation/architecture.md.
 */
class UpdateImportSettingRequest extends FormRequest
{
    /**
     * How many kilobytes are in one megabyte — the MB → KB conversion factor.
     */
    private const KILOBYTES_PER_MEGABYTE = 1024;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalise the raw form input before validation: `run_in_background` is a
     * checkbox, so absent means false (mirrors UpdateCrawlerSettingRequest's
     * `enabled` handling).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'run_in_background' => $this->boolean('run_in_background'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_archive_megabytes' => ['required', 'integer', 'min:1'],
            'run_in_background' => ['boolean'],
        ];
    }

    /**
     * The persistable attribute set for the ImportSetting singleton, with the
     * user-facing megabytes converted to the stored kilobytes unit.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'max_archive_kilobytes' => $this->integer('max_archive_megabytes') * self::KILOBYTES_PER_MEGABYTE,
            'run_in_background' => $this->boolean('run_in_background'),
        ];
    }
}
