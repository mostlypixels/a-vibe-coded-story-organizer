<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates appearance preferences for the current user. */
class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * A null value restores the configured default.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme_slug' => ['nullable', Rule::in(array_keys(config('themes.presets')))],
            'ui_font' => ['nullable', Rule::in(array_keys(config('fonts.families')))],
            'manuscript_font' => ['nullable', Rule::in(array_keys(config('fonts.families')))],
            'ui_scale' => ['nullable', Rule::in(array_keys(config('fonts.ui_scales')))],
            'manuscript_scale' => ['nullable', Rule::in(array_keys(config('fonts.manuscript_scales')))],
            'manuscript_leading' => ['nullable', Rule::in(array_keys(config('fonts.leading')))],
            'ui_leading' => ['nullable', Rule::in(array_keys(config('fonts.leading')))],
        ];
    }
}
