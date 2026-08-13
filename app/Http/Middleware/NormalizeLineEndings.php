<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Rewrites every incoming string to LF line endings.
 *
 * A browser sends CRLF for form fields (the HTML form-submission algorithm
 * normalizes them), but the autosave endpoint posts JSON, which keeps the LF
 * the textarea holds in memory. The same text therefore reached the database
 * with two different byte sequences, and a revision diff between a manual save
 * and an autosave showed every line as changed.
 *
 * This sits beside Laravel's own TrimStrings/ConvertEmptyStringsToNull for the
 * same reason they exist: the correction belongs to the request, not to each
 * field that could receive one.
 */
class NormalizeLineEndings extends TransformsRequest
{
    protected function transform($key, $value)
    {
        return is_string($value)
            ? str_replace(["\r\n", "\r"], "\n", $value)
            : $value;
    }
}
