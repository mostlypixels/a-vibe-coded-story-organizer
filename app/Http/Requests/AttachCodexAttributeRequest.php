<?php

namespace App\Http\Requests;

use App\Models\CodexAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttachCodexAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('codexEntry')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('codexEntry')->project;

        return [
            'codex_attribute_id' => [
                'required',
                'integer',
                Rule::exists('codex_attributes', 'id')->where('project_id', $project->id),
            ],
        ];
    }

    /**
     * The entry-type check needs the resolved attribute, so it runs here instead of a rule:
     * a picker error beats a 422 abort, since the field failing is still a normal pick.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('codex_attribute_id')) {
                return;
            }

            $codexEntry = $this->route('codexEntry');
            $attribute = CodexAttribute::find($this->input('codex_attribute_id'));

            if ($attribute !== null && ! $attribute->appliesTo($codexEntry->type)) {
                $validator->errors()->add('codex_attribute_id', 'This attribute does not apply to this entry type.');
            }
        });
    }
}
