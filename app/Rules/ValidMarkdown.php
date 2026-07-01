<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use League\CommonMark\CommonMarkConverter;
use Throwable;

class ValidMarkdown implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            (new CommonMarkConverter)->convert((string) $value);
        } catch (Throwable) {
            $fail('The :attribute must be valid Markdown.');
        }
    }
}
