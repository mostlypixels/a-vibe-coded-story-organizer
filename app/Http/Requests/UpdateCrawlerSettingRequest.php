<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCrawlerSettingRequest extends FormRequest
{
    /**
     * The crawler settings are GLOBAL — not owned by a Project or User — so this
     * is the one place that departs from the ProjectPolicy walk: any authenticated
     * user may edit them. The `auth` middleware already blocks guests; this simply
     * confirms a user is present. See documentation/architecture.md.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalise the raw form input before validation:
     * - `enabled` is a checkbox, so absent means false.
     * - the whitelist arrives as a textarea (one term per line); split it into a
     *   trimmed, blank-dropped, de-duplicated array so the DB stores clean terms.
     */
    protected function prepareForValidation(): void
    {
        $raw = (string) $this->input('user_agent_whitelist', '');

        $terms = collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn ($term) => trim($term))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'user_agent_whitelist' => $terms,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'user_agent_whitelist' => ['array'],
            // Line-safe: no CR/LF, ':' (directive separator) or '#' (comment) so a
            // term cannot forge robots.txt directives. This regex is the single
            // guard the RobotsTxtGenerator trusts.
            'user_agent_whitelist.*' => ['string', 'max:255', 'regex:/^[^\r\n:#]+$/'],
        ];
    }
}
