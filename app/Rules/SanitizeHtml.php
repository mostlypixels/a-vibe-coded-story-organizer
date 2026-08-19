<?php

namespace App\Rules;

use App\Services\HtmlSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/** Checks that rich HTML can pass through the sanitizer used during model writes. */
class SanitizeHtml implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be valid HTML.');

            return;
        }

        try {
            app(HtmlSanitizer::class)->clean($value);
        } catch (Throwable) {
            $fail('The :attribute must be valid HTML.');
        }
    }
}
