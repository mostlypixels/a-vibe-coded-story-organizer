<?php

namespace App\Http\Requests;

use App\Enums\CodexEntryType;
use App\Models\CodexEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The one-name-one-type entry created from the scene editor. Unlike
 * {@see StoreCodexEntryRequest}, a duplicate name is refused, not merely warned about — the
 * full form leaves its warning on screen to read, and this dialog closes on success.
 */
class StoreQuickCodexEntryRequest extends FormRequest
{
    private ?CodexEntry $duplicateEntry = null;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scene')->chapter->act->book->project);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CodexEntryType::class)],
        ];
    }

    /**
     * Refuses a name that case-insensitively matches an existing entry of the same type
     * in the project. Entry counts are small, so a plain collection compare is fine.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = $this->input('name');
            $type = $this->input('type');

            if (! is_string($name) || $name === '' || $type === null) {
                return;
            }

            $project = $this->route('scene')->chapter->act->book->project;

            $this->duplicateEntry = $project->codexEntries()
                ->where('type', $type)
                ->get()
                ->first(fn (CodexEntry $entry) => mb_strtolower($entry->name) === mb_strtolower($name));

            if ($this->duplicateEntry !== null) {
                $validator->errors()->add('name', __('An entry named :name already exists.', ['name' => $this->duplicateEntry->name]));
            }
        });
    }

    /** Carries the existing entry's id so the client can offer "Open it". */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
            'existing_entry_id' => $this->duplicateEntry?->id,
        ], 422));
    }
}
