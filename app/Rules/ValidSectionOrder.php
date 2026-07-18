<?php

namespace App\Rules;

use App\Models\PublicationSetting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a `PublicationSetting::section_order` array: it must contain each
 * of `PublicationSetting::SECTION_KEYS` exactly once (no duplicates, no
 * unknown keys) with `title` pinned first (overview decision #4).
 */
class ValidSectionOrder implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be an array.');

            return;
        }

        $expected = PublicationSetting::SECTION_KEYS;

        $sortedExpected = $expected;
        $sortedActual = $value;
        sort($sortedExpected);
        sort($sortedActual);

        if ($sortedActual !== $sortedExpected) {
            $fail('The :attribute must list each section exactly once: '.implode(', ', $expected).'.');

            return;
        }

        if (($value[0] ?? null) !== PublicationSetting::PINNED_FIRST_SECTION) {
            $fail('The :attribute must list "'.PublicationSetting::PINNED_FIRST_SECTION.'" first.');
        }
    }
}
