<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates an ISBN-13.
 *
 * The value is accepted as typed — hyphens and spaces are stripped only to
 * verify the digits and checksum, never to normalize the stored value (the
 * project keeps the author's formatting). An empty value is treated as valid so
 * this rule composes with `nullable`: it never runs on an absent ISBN.
 */
class ValidIsbn implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Pair with `nullable`: an empty ISBN is not this rule's concern.
        if ($value === null || $value === '') {
            return;
        }

        // Strip the punctuation authors use to group the ISBN's segments.
        $digits = preg_replace('/[\s-]/', '', (string) $value);

        if (! preg_match('/^\d{13}$/', $digits)) {
            $fail('The :attribute must be a valid 13-digit ISBN.');

            return;
        }

        if (! $this->hasValidChecksum($digits)) {
            $fail('The :attribute has an invalid ISBN checksum.');
        }
    }

    /**
     * ISBN-13 checksum: digits are weighted 1 and 3 alternately; the total
     * (check digit included) must be a multiple of 10.
     */
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
