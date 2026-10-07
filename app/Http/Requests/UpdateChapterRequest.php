<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsBaseHashes;
use App\Rules\NoAutosaveConflict;
use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChapterRequest extends FormRequest
{
    use ReadsBaseHashes;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chapter')->project());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'act_id' => [
                'required',
                'integer',
                // A chapter cannot move outside its current book.
                Rule::exists('acts', 'id')->where('book_id', $this->route('chapter')->act->book_id),
            ],
            ...StoreChapterRequest::fieldRules(),
            'description' => AutosavableFields::validationRule('chapter', 'description'),

            'cover_image' => CodexMediaRules::coverRules(),
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [new NoAutosaveConflict($this->route('chapter'), $this->baseHashes())];
    }
}
