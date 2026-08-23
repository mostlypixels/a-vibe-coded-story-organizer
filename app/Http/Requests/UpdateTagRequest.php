<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tag')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Tag names are unique within a project; the tag keeps its own name.
                Rule::unique('tags', 'name')->where('project_id', $tag->project_id)->ignore($tag->id),
            ],
        ];
    }
}
