<?php

namespace App\Http\Requests;

use App\Rules\SiblingDestination;
use Illuminate\Foundation\Http\FormRequest;

/** The destination must be a different chapter in the same book. */
class DestroyChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chapter')->project());
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
                new SiblingDestination($chapter->book()->chapterQuery(), except: $chapter),
            ],
        ];
    }
}
