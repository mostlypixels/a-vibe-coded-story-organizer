<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorizes an EPUB export through the selected book's project. */
class EpubExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $book = Book::find($this->input('book_id'));

        return $book !== null && $this->user()->can('view', $book->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')],
        ];
    }
}
