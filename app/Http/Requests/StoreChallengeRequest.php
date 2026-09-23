<?php

namespace App\Http\Requests;

use App\Enums\ChallengeRecurrence;
use App\Rules\MaxChallengeWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** UpdateChallengeRequest and the archive import use the same rules. */
class StoreChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Rules that need no route model, so the archive import can use them.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'recurrence' => ['required', Rule::enum(ChallengeRecurrence::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required_if:recurrence,none', 'nullable', 'date', 'after_or_equal:starts_on', new MaxChallengeWindow],
            'target_words' => ['required', 'integer', 'min:1', 'max:10000000'],
        ];
    }
}
