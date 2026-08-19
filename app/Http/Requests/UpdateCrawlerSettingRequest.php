<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCrawlerSettingRequest extends FormRequest
{
    /** Crawler settings have no owning project. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Converts the checkbox and whitelist textarea to stored values. */
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
            // Block characters that can create another robots.txt directive.
            'user_agent_whitelist.*' => ['string', 'max:255', 'regex:/^[^\r\n:#]+$/'],
        ];
    }
}
