<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The destination must be a different act in the same book. */
class DestroyActRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('act')->book->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $act = $this->route('act');

        return [
            'move_children_to' => [
                'nullable',
                Rule::exists('acts', 'id')->where('book_id', $act->book_id),
                Rule::notIn([$act->id]),
            ],
        ];
    }
}
