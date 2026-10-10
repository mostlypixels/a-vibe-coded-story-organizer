<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsBaseHashes;
use App\Rules\NoAutosaveConflict;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoteRequest extends FormRequest
{
    use ReadsBaseHashes;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('note')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'note_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('note_categories', 'id')->where('project_id', $this->route('note')->project_id)],
            'body' => AutosavableFields::validationRule('note', 'body'),
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [new NoAutosaveConflict($this->route('note'), $this->baseHashes())];
    }
}
