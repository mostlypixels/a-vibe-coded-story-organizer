<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an update to the acting user's own appearance preferences:
 * {@see User::$theme_slug}, font family, scale and leading columns.
 *
 * Like {@see UpdateCrawlerSettingRequest} and {@see UpdateImportSettingRequest},
 * this uses the any-authenticated-user exception rather than a ProjectPolicy
 * walk — but for a different reason than those two: it isn't a global setting
 * owned by no one, it's a per-user preference the action writes only to
 * `$request->user()`. There is no cross-user case to authorize at all. See
 * documentation/architecture.md.
 */
class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * `null` is valid on every field — it clears the preference back to its
     * `config('fonts.default_*')` or `config('themes.default')` value.
     * Anything else must name a configured slug; never a free string reaching
     * ThemeStyleBlock or FontStyleBlock.
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
