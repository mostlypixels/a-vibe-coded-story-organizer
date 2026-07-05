<?php

namespace App\Http\Requests;

use App\Rules\SanitizeHtml;
use App\Support\PlotlineColors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlotlineRequest extends FormRequest
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
            'description' => ['nullable', 'string', new SanitizeHtml],
            'color' => [
                'required',
                'string',
                Rule::in(PlotlineColors::PRESETS),
                Rule::unique('plotlines')->where('project_id', $this->route('project')->id),
            ],
        ];
    }
}
