<?php

namespace App\Http\Requests;

use App\Enums\CodexEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCodexAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mirror ProjectPolicy@update: only the owner may add attributes to a project.
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // At least one entry type must show the attribute, and every submitted
            // value must be a valid CodexEntryType (no arbitrary sheet keys).
            'applies_to' => ['required', 'array', 'min:1'],
            'applies_to.*' => [Rule::enum(CodexEntryType::class)],
        ];
    }
}
