<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChapterRequest extends FormRequest
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
            'act_id' => [
                'required',
                'integer',
                Rule::exists('acts', 'id')->where('book_id', $this->route('book')->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('chapter', 'description'),
        ];
    }
}
