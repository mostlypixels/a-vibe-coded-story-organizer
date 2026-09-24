<?php

namespace App\Http\Requests;

use App\Rules\SiblingDestination;
use Illuminate\Foundation\Http\FormRequest;

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
                new SiblingDestination($act->book->acts(), except: $act),
            ],
        ];
    }
}
