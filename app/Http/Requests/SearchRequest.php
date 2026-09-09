<?php

namespace App\Http\Requests;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Models\Chapter;
use App\Support\SearchScopeFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Blank queries show the search form without results.
 *
 * `book`, `from_chapter`, `to_chapter`, and `domains` narrow the search (see
 * {@see SearchScopeFactory}). Route-model binding does not cover
 * them — they arrive as query parameters — so a cross-project id fails
 * validation here instead of being silently ignored.
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
            'book' => [
                'nullable',
                'integer',
                Rule::exists('books', 'id')->where(
                    fn ($query) => $query->where('project_id', $this->route('project')->id)
                ),
            ],
            'from_chapter' => ['nullable', 'integer', 'exists:chapters,id'],
            'to_chapter' => ['nullable', 'integer', 'exists:chapters,id'],
            'domains' => ['nullable', 'array'],
            'domains.*' => [Rule::enum(SearchDomain::class)],
        ];
    }

    /**
     * Cross-checks that depend on `book`, which arrives in this same request
     * and so cannot be expressed as a plain `exists` rule.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bookId = $this->filled('book') ? $this->integer('book') : null;

            foreach (['from_chapter', 'to_chapter'] as $field) {
                if (! $this->filled($field)) {
                    continue;
                }

                if ($bookId === null) {
                    $validator->errors()->add($field, __('A chapter range needs a book.'));

                    continue;
                }

                $belongsToBook = Chapter::query()
                    ->whereKey($this->integer($field))
                    ->whereHas('act', fn ($query) => $query->where('book_id', $bookId))
                    ->exists();

                if (! $belongsToBook) {
                    $validator->errors()->add($field, __('The selected chapter does not belong to the chosen book.'));
                }
            }
        });
    }
}
