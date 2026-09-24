<?php

namespace App\Http\Requests;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The save ID is only a lookup key. Access comes from the owning project.
 * The base hashes let the reverter refuse fields that changed since the page loaded.
 */
class RevertSaveRequest extends FormRequest
{
    /** @var Collection<int, Revision>|null */
    private ?Collection $saveGroup = null;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->saveGroup()->first()->owningProject());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_hashes' => ['required', 'array'],
            'base_hashes.*' => ['required', 'string'],
        ];
    }

    /** @return Collection<int, Revision> The revisions of one save, without their values. */
    public function saveGroup(): Collection
    {
        if ($this->saveGroup === null) {
            $this->saveGroup = Revision::query()
                ->where('save_id', $this->route('save'))
                ->select(['id', 'save_id', 'field', 'created_at', 'origin', 'revisionable_type', 'revisionable_id', 'project_id'])
                ->get();

            abort_if($this->saveGroup->isEmpty(), 404);
        }

        return $this->saveGroup;
    }
}
