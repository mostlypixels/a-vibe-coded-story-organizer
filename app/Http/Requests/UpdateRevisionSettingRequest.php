<?php

namespace App\Http\Requests;

use App\Models\RevisionSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an update to the global {@see RevisionSetting} singleton's
 * `retention_days`.
 *
 * Like {@see UpdateImportSettingRequest} and {@see UpdateCrawlerSettingRequest}, the
 * revision settings are GLOBAL (owned by no Project or User), so this uses the
 * any-authenticated-user exception: the `auth` + `access-admin` middleware blocks
 * guests; this confirms a user is present. See documentation/architecture.md.
 *
 * The confirm-gated "lowering destroys history" step (handoff.md §9.11) lives in
 * RevisionSettingController, not here — this request only validates the raw bounds
 * (min:7, max:3650) that apply regardless of whether the value is being raised or
 * lowered, or whether this is the first or the confirming submission.
 */
class UpdateRevisionSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'confirmed' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }
}
