<?php

namespace App\Http\Requests;

use App\Rules\NoteLinkTarget;
use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::fieldRules(),
            'note_category_id' => ['nullable', 'integer', Rule::exists('note_categories', 'id')->where('project_id', $this->route('project')->id)],
            'body' => AutosavableFields::validationRule('note', 'body'),
            // "New note" on an entity page passes `<type>:<id>` to link the new note.
            'link' => ['nullable', 'string', new NoteLinkTarget($this->route('project'))],
        ];
    }

    /**
     * Rules that need no route model, so the archive import can use them.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    /** The entity to link the new note to, if any. Call it only after validation passes. */
    public function linkTarget(): ?Model
    {
        return NoteLinkTarget::resolveKey($this->route('project'), $this->input('link'));
    }
}
