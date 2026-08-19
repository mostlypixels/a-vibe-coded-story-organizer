<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The destination must be a different chapter in the same book. */
class DestroyChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chapter')->act->book->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $chapter = $this->route('chapter');

        return [
            'move_children_to' => [
                'nullable',
                Rule::exists('chapters', 'id')->whereIn('act_id', $chapter->act->book->acts()->pluck('id')),
                Rule::notIn([$chapter->id]),
            ],
        ];
    }
}
