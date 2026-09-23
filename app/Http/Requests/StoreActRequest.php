<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreActRequest extends FormRequest
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
            ...self::fieldRules(),
            'description' => AutosavableFields::validationRule('act', 'description'),
        ];
    }

    /**
     * Rules that need no route model, so the archive import can use them.
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
