<?php

namespace App\Http\Requests;

use App\Enums\CodexEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCodexAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Walk up to the owning project (the flat route only binds the attribute).
        return $this->user()->can('update', $this->route('codexAttribute')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', 'array', 'min:1'],
            'applies_to.*' => [Rule::enum(CodexEntryType::class)],
        ];
    }
}
