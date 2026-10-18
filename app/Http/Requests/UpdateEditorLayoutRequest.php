<?php

namespace App\Http\Requests;

use App\Support\EditorLayout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the per-user layout of the Write page: panel widths and collapse state. */
class UpdateEditorLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return EditorLayout::rules();
    }
}
