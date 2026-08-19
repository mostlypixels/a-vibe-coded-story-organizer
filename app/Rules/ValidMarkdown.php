<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Throwable;

class ValidMarkdown implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            // Validate the same GFM grammar that renders scene contents.
            (new GithubFlavoredMarkdownConverter)->convert((string) $value);
        } catch (Throwable) {
            $fail('The :attribute must be valid Markdown.');
        }
    }
}
