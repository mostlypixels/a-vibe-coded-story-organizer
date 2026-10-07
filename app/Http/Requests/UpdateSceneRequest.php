<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsBaseHashes;
use App\Rules\NoAutosaveConflict;
use Illuminate\Foundation\Http\FormRequest;

/** Same rules as StoreSceneRequest, for the book that holds the scene. */
class UpdateSceneRequest extends FormRequest
{
    use ReadsBaseHashes;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scene')->project());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return StoreSceneRequest::rulesFor($this->route('scene')->book());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StoreSceneRequest::fieldAttributes();
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [new NoAutosaveConflict($this->route('scene'), $this->baseHashes())];
    }
}
