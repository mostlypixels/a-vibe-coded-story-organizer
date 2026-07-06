<?php

namespace App\Http\Requests;

use App\Rules\SanitizeHtml;
use App\Rules\WithinEventWindow;
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
            'description' => ['nullable', 'string', new SanitizeHtml],
            // Start/End bookends are editable, but every event stays inside the [Start, End]
            // window and the bookends stay first/last — WithinEventWindow enforces both, and
            // branches on the bookend being edited so Start/End bound only themselves.
            'event_datetime' => ['required', 'date', new WithinEventWindow($this->route('event')->project, $this->route('event'))],
            'plotlines' => ['required', 'array', 'min:1'],
            'plotlines.*' => [
                'integer',
                Rule::exists('plotlines', 'id')->where('project_id', $this->route('event')->project_id),
            ],
        ];
    }
}
