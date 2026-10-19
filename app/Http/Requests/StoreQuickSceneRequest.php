<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The Write page "New scene" dialog: only a chapter and a title. */
class StoreQuickSceneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('book')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chapter_id' => StoreSceneRequest::rulesFor($this->route('book'))['chapter_id'],
            ...StoreSceneRequest::fieldRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return StoreSceneRequest::fieldAttributes();
    }
}
