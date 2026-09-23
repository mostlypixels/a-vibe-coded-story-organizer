<?php

namespace App\Rules;

use App\Enums\ChallengeRecurrence;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Caps a one-off challenge window at 366 days, on the `ends_on` field.
 *
 * The standing makes one entry per day in PHP, so a long window is slow.
 * `ShowProgressRequest` allows the same span.
 * A monthly challenge is a series of month-long windows, so the cap does not apply.
 */
class MaxChallengeWindow implements DataAwareRule, ValidationRule
{
    public const MAX_DAYS = 366;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $startsOn = $this->data['starts_on'] ?? null;

        if (blank($value) || blank($startsOn) || ($this->data['recurrence'] ?? null) !== ChallengeRecurrence::None->value) {
            return;
        }

        try {
            $span = CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($value));
        } catch (Throwable) {
            return; // The `date` rule reports a bad date.
        }

        if ($span > self::MAX_DAYS) {
            $fail(__('The window cannot span more than 366 days.'));
        }
    }
}
