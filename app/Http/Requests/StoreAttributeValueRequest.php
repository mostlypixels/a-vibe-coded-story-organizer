<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mirror ProjectPolicy@update: only the owner may edit an entry's timeline.
        return $this->user()->can('update', $this->route('codexEntry')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('codexEntry')->project;

        return [
            // Same cross-project rigor as StoreSceneRequest: the anchor must be an event
            // of the entry's own project. No Rule::unique — the endpoint is an upsert and
            // the DB unique key on (entry, attribute, start_event) is only a backstop.
            'start_event_id' => [
                'required',
                'integer',
                Rule::exists('events', 'id')->where('project_id', $project->id),
            ],
            // 'present' (not 'required'): an empty value is a first-class "recorded as blank"
            // value (binding decision Q2) — required would make an empty baseline unsavable and
            // prevent clearing a value back to blank. 'nullable' because the global
            // ConvertEmptyStringsToNull middleware turns a blank text input's "" into null; the
            // controller casts (string) back before upsertAt (its signature is string $value).
            'value' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
