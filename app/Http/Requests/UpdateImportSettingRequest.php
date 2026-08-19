<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Converts the displayed megabyte limit to the stored kilobyte limit. */
class UpdateImportSettingRequest extends FormRequest
{
    /** Kilobytes in one megabyte. */
    private const KILOBYTES_PER_MEGABYTE = 1024;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Converts an absent checkbox to false. */
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

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'max_archive_kilobytes' => $this->integer('max_archive_megabytes') * self::KILOBYTES_PER_MEGABYTE,
            'run_in_background' => $this->boolean('run_in_background'),
        ];
    }
}
