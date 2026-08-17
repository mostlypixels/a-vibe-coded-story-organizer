<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('act', 'description'),
        ];
    }
}
