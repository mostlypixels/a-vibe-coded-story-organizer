<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSceneShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scene')->chapter->act->book->project);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'duration' => ['required', Rule::in(array_values(config('sharing.scene_link_durations')))],
        ];
    }
}
