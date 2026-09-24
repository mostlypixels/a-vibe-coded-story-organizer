<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The base hash lets the reverter refuse a field that changed since the page loaded. */
class RevertRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('revision')->owningProject());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_hash' => ['required', 'string'],
        ];
    }
}
