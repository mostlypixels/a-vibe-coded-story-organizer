<?php

namespace App\Http\Requests;

use App\Enums\CodexEntryType;
use App\Rules\SanitizeHtml;
use App\Support\CodexMediaRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCodexEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mirror ProjectPolicy@update: only the owner may add entries to a project.
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', new SanitizeHtml],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:255'],

            // Start-anchored baseline value per applicable attribute, keyed by attribute id
            // (attribute_baselines[<codex_attribute_id>] => value). Values are validated here;
            // the keys (project scope + applies_to) are checked in withValidator below, because
            // applies_to is a JSON column filtered in PHP rather than via a DB rule.
            'attribute_baselines' => ['nullable', 'array'],
            'attribute_baselines.*' => ['nullable', 'string', 'max:255'],

            // Media uploads. Rules are centralized in CodexMediaRules so store/update
            // (and the form hints) never drift. No remove_media[] here: a brand-new
            // entry has nothing to remove — that only applies on update.
            'cover' => CodexMediaRules::coverRules(),
            'reference_images' => ['nullable', 'array'],
            'reference_images.*' => CodexMediaRules::referenceImageRules(),
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => CodexMediaRules::referenceFileRules(),
        ];
    }

    /**
     * Validate the attribute_baselines *keys*: each must be an attribute of this project
     * whose applies_to includes the entry type — the same cross-project rigor applied to
     * anchor events. Done here (not as array rules) because applies_to is JSON checked in PHP.
     */
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
