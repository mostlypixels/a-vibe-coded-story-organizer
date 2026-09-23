<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same rules as StoreChallengeRequest. Edits are silent: no revision, no
 * lock, and a changed target or window re-scores every past day.
 */
class UpdateChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('challenge')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return StoreChallengeRequest::fieldRules();
    }
}
