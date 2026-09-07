<?php

namespace App\Http\Requests;

use App\Support\PageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the per-user row count an entity list paginates by. */
class UpdatePageSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'page_size' => ['required', Rule::in(PageSize::sizes())],
        ];
    }
}
