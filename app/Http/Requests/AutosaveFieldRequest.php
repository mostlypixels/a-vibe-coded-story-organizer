<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One autosaved field value. The registry gives the rule for each field.
 *
 * Autosave routes name the entity by registry slug and ID, not by route-model
 * binding, so this request resolves the entity for the controller.
 * An unregistered field is a 404 before any access check.
 */
class AutosaveFieldRequest extends FormRequest
{
    private ?Model $autosavable = null;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->autosavable()->revisionProject());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => AutosavableFields::validationRule($this->route('entity'), $this->route('field')),
            'base_hash' => ['required', 'string'],
            'run_matcher' => ['sometimes', 'boolean'],
        ];
    }

    public function autosavable(): Model
    {
        if ($this->autosavable === null) {
            [$modelClass] = AutosavableFields::resolveField($this->route('entity'), $this->route('field'));

            $this->autosavable = $modelClass::findOrFail($this->route('id'));
        }

        return $this->autosavable;
    }
}
