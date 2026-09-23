<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
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
            'name' => [
                ...self::fieldRules()['name'],
                // Tag names are unique within a project.
                Rule::unique('tags', 'name')->where('project_id', $this->route('project')->id),
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
        ];
    }
}
