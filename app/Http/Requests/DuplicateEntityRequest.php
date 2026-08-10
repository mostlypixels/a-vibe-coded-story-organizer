<?php

namespace App\Http\Requests;

use App\Support\RouteProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by both duplicate routes (scenes and codex entries). `RouteProject`
 * already walks any bound route model up to its project, so one class
 * authorizes both without a per-entity subclass — a future duplicable entity
 * needs no new Form Request, only a `RouteProject` case.
 *
 * This is the only authorization check on either duplicate action: nothing in
 * the controller runs before validation, so there is no second
 * `$this->authorize()` call there.
 */
class DuplicateEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', RouteProject::resolve($this));
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
