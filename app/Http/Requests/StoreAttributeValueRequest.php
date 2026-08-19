<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeValueRequest extends FormRequest
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
            // The endpoint is an upsert, so the start event does not need a unique rule.
            'start_event_id' => [
                'required',
                'integer',
                Rule::exists('events', 'id')->where('project_id', $project->id),
            ],
            // A present null value records an intentional blank after middleware conversion.
            'value' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
