<?php

namespace App\Http\Requests;

use App\Support\RouteContext;
use Illuminate\Foundation\Http\FormRequest;

/** Authorizes duplication through the project found by {@see RouteContext}. */
class DuplicateEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', RouteContext::resolve($this)->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
