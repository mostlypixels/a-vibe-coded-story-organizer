<?php

namespace App\Http\Requests;

use App\Enums\SceneStatus;
use App\Rules\ValidMarkdown;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSceneRequest extends FormRequest
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
            'chapter_id' => [
                'required',
                'integer',
                Rule::exists('chapters', 'id')->whereIn('act_id', $this->route('project')->acts()->pluck('id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contents' => ['nullable', 'string', new ValidMarkdown],
            'notes' => ['nullable', 'string', new ValidMarkdown],
            'status' => ['required', Rule::enum(SceneStatus::class)],
        ];
    }
}
