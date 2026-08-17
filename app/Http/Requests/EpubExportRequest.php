<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes an epub export.
 *
 * Mirrors {@see ExportRequest}: the /admin area sits behind the `access-admin`
 * gate (any authenticated user), which is NOT ownership. Because the export reads
 * one user-owned book, this request also walks up to the book's project via
 * ProjectPolicy: a foreign or missing book_id is a 403, never a silent export of
 * another user's book. The controller mirrors the same authorize('view', $book->project)
 * check.
 *
 * There is no `include_images`-equivalent option in v1 — the epub never embeds
 * Codex media, so the only input is the book_id.
 */
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
