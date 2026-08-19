<?php

namespace App\Http\Requests;

use App\Enums\SearchMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Blank queries show the search form without results. */
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
