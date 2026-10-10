<?php

namespace App\Http\Requests;

use App\Rules\ValidNoteCategoryParent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteCategoryRequest extends FormRequest
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
        $project = $this->route('project');

        return [
            // Unique among siblings; the null parent is a sibling group too.
            'name' => [
                ...self::fieldRules()['name'],
                Rule::unique('note_categories')
                    ->where('project_id', $project->id)
                    ->where('parent_id', $this->integer('parent_id') ?: null),
            ],
            'parent_id' => ['nullable', 'integer', new ValidNoteCategoryParent($project)],
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
