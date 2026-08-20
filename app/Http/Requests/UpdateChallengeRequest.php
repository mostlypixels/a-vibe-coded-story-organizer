<?php

namespace App\Http\Requests;

use App\Enums\ChallengeRecurrence;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'recurrence' => ['required', Rule::enum(ChallengeRecurrence::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => [
                'required_if:recurrence,none',
                'nullable',
                'date',
                'after_or_equal:starts_on',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (blank($value) || ! $this->filled('starts_on') || $this->input('recurrence') !== ChallengeRecurrence::None->value) {
                        return;
                    }

                    $span = CarbonImmutable::parse($this->input('starts_on'))->diffInDays(CarbonImmutable::parse($value));

                    if ($span > 366) {
                        $fail(__('The window cannot span more than 366 days.'));
                    }
                },
            ],
            'target_words' => ['required', 'integer', 'min:1', 'max:10000000'],
        ];
    }
}
