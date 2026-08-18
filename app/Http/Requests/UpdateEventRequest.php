<?php

namespace App\Http\Requests;

use App\Rules\WithinEventWindow;
use App\Support\AutosavableFields;
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
            'description' => AutosavableFields::validationRule('event', 'description'),
            // The Start and End events must remain the first and last events.
            'event_datetime' => ['required', 'date', new WithinEventWindow($this->route('event')->project, $this->route('event'))],
            'plotlines' => ['required', 'array', 'min:1'],
            'plotlines.*' => [
                'integer',
                Rule::exists('plotlines', 'id')->where('project_id', $this->route('event')->project_id),
            ],
        ];
    }
}
