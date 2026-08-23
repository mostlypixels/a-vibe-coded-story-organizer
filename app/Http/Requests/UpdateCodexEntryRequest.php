<?php

namespace App\Http\Requests;

use App\Rules\WithinEventWindow;
use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCodexEntryRequest extends FormRequest
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

        // A regular (non-bookend) event, scoped to the project — the same shape for
        // both lifespan links, so this closure builds the exists rule for each.
        $regularEvent = fn () => Rule::exists('events', 'id')
            ->where('project_id', $project->id)
            ->where('is_fixed', 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('codex', 'description'),
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:255'],

            // No cross-field ordering rule: termination before inception is a legal
            // save (time travel) — hasInvertedLifespan() handles the fallout.
            'inception_event_id' => ['nullable', 'integer', $regularEvent()],
            'new_inception_event_title' => ['nullable', 'string', 'max:255', 'required_with:new_inception_event_datetime'],
            'new_inception_event_datetime' => ['nullable', 'date', 'required_with:new_inception_event_title', new WithinEventWindow($project)],
            'termination_event_id' => ['nullable', 'integer', $regularEvent()],
            'new_termination_event_title' => ['nullable', 'string', 'max:255', 'required_with:new_termination_event_datetime'],
            'new_termination_event_datetime' => ['nullable', 'date', 'required_with:new_termination_event_title', new WithinEventWindow($project)],

            'cover' => CodexMediaRules::coverRules(),
            'reference_images' => ['nullable', 'array'],
            'reference_images.*' => CodexMediaRules::referenceImageRules(),
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => CodexMediaRules::referenceFileRules(),

            // A removal ID must belong to this entry.
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => [
                'integer',
                Rule::exists('codex_media', 'id')->where('codex_entry_id', $this->route('codexEntry')->id),
            ],

        ];
    }
}
