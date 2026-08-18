<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/** Validates an ISBN-13 without changing the author's format. */
class ValidIsbn implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $digits = preg_replace('/[\s-]/', '', (string) $value);

        if (! preg_match('/^\d{13}$/', $digits)) {
            $fail('The :attribute must be a valid 13-digit ISBN.');

            return;
        }

        if (! $this->hasValidChecksum($digits)) {
            $fail('The :attribute has an invalid ISBN checksum.');
        }
    }

    /** The ISBN-13 checksum uses alternating weights of 1 and 3. */
    private function hasValidChecksum(string $digits): bool
    {
        $sum = 0;

        for ($position = 0; $position < 13; $position++) {
            $weight = $position % 2 === 0 ? 1 : 3;
            $sum += ((int) $digits[$position]) * $weight;
        }

        return $sum % 10 === 0;
    }
}
