<?php

namespace App\Rules;

use App\Services\HtmlSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/**
 * Reusable rule attached to every rich-HTML field in the Form Requests (task 02),
 * mirroring how `new ValidMarkdown` guards the Markdown fields. It validates that
 * the value is processable HTML; the actual cleaning/stripping is HtmlSanitizer's
 * job, run on the model write path so the stored value is always safe.
 */
class SanitizeHtml implements ValidationRule
{
    /**
     * Run the validation rule.
     *
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
