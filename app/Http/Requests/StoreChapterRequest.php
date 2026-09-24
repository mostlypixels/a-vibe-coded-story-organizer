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
            ...self::fieldRules(),
            'description' => AutosavableFields::validationRule('chapter', 'description'),
        ];
    }

    /**
     * Rules that need no route model, so the Update request and the archive import can use them.
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
