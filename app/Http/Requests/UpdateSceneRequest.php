<?php

namespace App\Http\Requests;

use App\Rules\ValidMarkdown;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSceneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scene')->chapter->act->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('scene')->chapter->act->project;

        return [
            'chapter_id' => [
                'required',
                'integer',
                Rule::exists('chapters', 'id')->whereIn('act_id', $project->acts()->pluck('id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contents' => ['nullable', 'string', new ValidMarkdown],
        ];
    }
}
