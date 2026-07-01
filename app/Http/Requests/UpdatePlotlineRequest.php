<?php

namespace App\Http\Requests;

use App\Support\PlotlineColors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlotlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plotline')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => [
                'required',
                'string',
                Rule::in(PlotlineColors::PRESETS),
                Rule::unique('plotlines')
                    ->where('project_id', $this->route('plotline')->project_id)
                    ->ignore($this->route('plotline')),
            ],
        ];
    }
}
