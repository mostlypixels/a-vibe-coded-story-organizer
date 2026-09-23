<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
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
        $fieldRules = self::fieldRules();

        return [
            ...$fieldRules,
            'description' => AutosavableFields::validationRule('plotline', 'description'),
            'color' => [
                ...$fieldRules['color'],
                Rule::unique('plotlines')->where('project_id', $this->route('project')->id),
            ],
        ];
    }

    /**
     * Rules that need no route model, so the archive import can use them.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', Rule::in(PlotlineColors::PRESETS)],
        ];
    }
}
