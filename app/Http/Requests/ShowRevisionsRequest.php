<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the history and compare pages of one entity.
 *
 * Revision routes name the entity by registry slug and ID, not by route-model
 * binding, so this request resolves the entity for the controller.
 */
class ShowRevisionsRequest extends FormRequest
{
    private ?Model $revisionable = null;

    public function authorize(): bool
    {
        return $this->user()->can('view', $this->revisionable()->revisionProject());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['nullable', 'string'],
            'label' => ['nullable', 'string'],
            'manual' => ['nullable', 'boolean'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
        ];
    }

    public function revisionable(): Model
    {
        return $this->revisionable ??= AutosavableFields::modelFor($this->route('entity'))::findOrFail($this->route('id'));
    }

    /** Null means all fields. An unregistered field is a 404, like an unknown page. */
    public function fieldFilter(): ?string
    {
        $field = trim((string) $this->validated('field'));

        if ($field === '') {
            return null;
        }

        AutosavableFields::resolveField($this->route('entity'), $field);

        return $field;
    }
}
