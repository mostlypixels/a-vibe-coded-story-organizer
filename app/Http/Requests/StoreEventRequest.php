<?php

namespace App\Http\Requests;

use App\Enums\CodexEntryType;
use App\Rules\WithinEventWindow;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** An unchecked checkbox sends nothing. */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_approximate' => $this->boolean('is_approximate')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fieldRules = self::fieldRules();

        return [
            ...$fieldRules,
            ...self::locationRules($this->route('project')->id),
            'description' => AutosavableFields::validationRule('event', 'description'),
            'event_datetime' => [...$fieldRules['event_datetime'], new WithinEventWindow($this->route('project'))],
            'plotlines' => ['required', 'array', 'min:1'],
            'plotlines.*' => [
                'integer',
                Rule::exists('plotlines', 'id')->where('project_id', $this->route('project')->id),
            ],
        ];
    }

    /**
     * Rules that need no route model, so the Update request and the archive import can use them.
     * The import checks the event window against the archive bookends.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'event_datetime' => ['required', 'date'],
            'is_approximate' => ['boolean'],
        ];
    }

    /**
     * Only Location entries of the same project.
     *
     * @return array<string, mixed>
     */
    public static function locationRules(int $projectId): array
    {
        return [
            'locations' => ['nullable', 'array'],
            'locations.*' => [
                'integer',
                Rule::exists('codex_entries', 'id')
                    ->where('project_id', $projectId)
                    ->where('type', CodexEntryType::Location->value),
            ],
        ];
    }
}
