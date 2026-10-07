<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsBaseHashes;
use App\Rules\NoAutosaveConflict;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActRequest extends FormRequest
{
    use ReadsBaseHashes;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('act')->book->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...StoreActRequest::fieldRules(),
            'description' => AutosavableFields::validationRule('act', 'description'),
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [new NoAutosaveConflict($this->route('act'), $this->baseHashes())];
    }
}
