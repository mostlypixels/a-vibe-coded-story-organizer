<?php

namespace App\Http\Requests;

use App\Enums\SearchMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a project search request.
 *
 * Authorization mirrors the controller: search reads a project's contents, so it
 * flows from the project via ProjectPolicy::view (CLAUDE.md § Authorization).
 *
 * `q` is deliberately nullable — an absent/blank query is the normal landing
 * state (render the form, no results), never a validation error. `mode` is an
 * optional SearchMode; the controller applies the AND default when it is absent,
 * so it is validated (a bad value fails) but not required.
 */
class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:500'],
            'mode' => ['nullable', Rule::enum(SearchMode::class)],
        ];
    }
}
