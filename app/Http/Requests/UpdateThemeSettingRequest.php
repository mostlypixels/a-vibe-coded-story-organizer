<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an update to the acting user's own {@see User::$theme_slug}.
 *
 * Like {@see UpdateCrawlerSettingRequest} and {@see UpdateImportSettingRequest},
 * this uses the any-authenticated-user exception rather than a ProjectPolicy
 * walk — but for a different reason than those two: it isn't a global setting
 * owned by no one, it's a per-user preference the action writes only to
 * `$request->user()`. There is no cross-user case to authorize at all. See
 * documentation/architecture.md.
 */
class UpdateThemeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * `null` is a valid value — it clears the preference back to
     * config('themes.default'). Anything else must name a configured preset;
     * never a free string reaching ThemeStyleBlock.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme_slug' => ['nullable', Rule::in(array_keys(config('themes.presets')))],
        ];
    }
}
