<?php

namespace App\Http\Requests;

use App\Enums\CodexEntryType;
use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCodexEntryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('codex', 'description'),
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:255'],

            // withValidator checks the project and entry type for each attribute key.
            'attribute_baselines' => ['nullable', 'array'],
            'attribute_baselines.*' => ['nullable', 'string', 'max:255'],

            'cover' => CodexMediaRules::coverRules(),
            'reference_images' => ['nullable', 'array'],
            'reference_images.*' => CodexMediaRules::referenceImageRules(),
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => CodexMediaRules::referenceFileRules(),
        ];
    }

    /** Checks each JSON-backed attribute key against the project and entry type. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $baselines = $this->input('attribute_baselines');

            if (! is_array($baselines) || $baselines === []) {
                return;
            }

            $project = $this->route('project');
            $type = CodexEntryType::fromRouteKey($this->route('type'));
            $attributes = $project->codexAttributes()->get()->keyBy('id');

            foreach (array_keys($baselines) as $attributeId) {
                $attribute = $attributes->get($attributeId);

                if ($attribute === null) {
                    $validator->errors()->add("attribute_baselines.{$attributeId}", __('The selected attribute is invalid.'));

                    continue;
                }

                if (! $attribute->appliesTo($type)) {
                    $validator->errors()->add("attribute_baselines.{$attributeId}", __('This attribute does not apply to this entry type.'));
                }
            }
        });
    }
}
