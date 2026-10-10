<?php

namespace App\Http\Requests;

use App\Rules\ValidNoteCategoryParent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('noteCategory')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('noteCategory');

        // Without `parent_id` the category stays where it is: a rename only.
        $parentId = $this->has('parent_id') ? ($this->integer('parent_id') ?: null) : $category->parent_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('note_categories')
                    ->where('project_id', $category->project_id)
                    ->where('parent_id', $parentId)
                    ->ignore($category),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', new ValidNoteCategoryParent($category->project, $category)],
        ];
    }
}
