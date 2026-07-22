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
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            // GFM (not bare CommonMark) so validation recognizes the same grammar
            // Scene::renderedContents() already renders via Str::markdown() —
            // strikethrough (~~text~~) and task lists ([ ]/[x]) included.
            (new GithubFlavoredMarkdownConverter)->convert((string) $value);
        } catch (Throwable) {
            $fail('The :attribute must be valid Markdown.');
        }
    }
}
