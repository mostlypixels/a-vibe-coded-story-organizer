<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Start/End bookends anchor timeline resolution, so their datetime is frozen:
            // tampering the field back into the payload yields a visible validation error.
            'event_datetime' => $this->route('event')->is_fixed
                ? ['prohibited']
                : ['required', 'date'],
            'plotlines' => ['required', 'array', 'min:1'],
            'plotlines.*' => [
                'integer',
                Rule::exists('plotlines', 'id')->where('project_id', $this->route('event')->project_id),
            ],
        ];
    }
}
