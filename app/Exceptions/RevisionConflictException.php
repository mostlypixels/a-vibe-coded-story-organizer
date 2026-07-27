<?php

namespace App\Exceptions;

use App\Services\RevisionReverter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thrown by {@see RevisionReverter} when a revert is asked to overwrite a value
 * that has moved since the page the request came from was rendered.
 *
 * Like {@see ImportValidationException}, this is a **user situation, not a bug**:
 * two tabs, or a still-running autosave, wrote the field after the history page
 * was drawn. The base-hash check is what stops the revert from silently
 * clobbering that newer text, and the message is user-safe by construction — it
 * says what happened and what to do, and never names a column or a hash.
 *
 * The controller turns it into a redirect-back-with-an-error-alert (the same
 * shape ImportController uses for its own validation exception). The 409 *status*
 * lives only on the JSON autosave endpoint, where a client actually reads it; a
 * writer who clicked a button gets a page they can act on instead of a bare
 * error screen.
 */
class RevisionConflictException extends RuntimeException
{
    /**
     * The field's stored value no longer hashes to the base hash the form
     * carried, so something else wrote it in the meantime.
     *
     * **Name the field.** A compare page shows several fields at once, each with
     * its own revert button, so "this changed" left the writer to guess which of
     * them moved. `Str::headline()` turns the column name into the same label
     * the rest of the feature shows a writer (`contents` → "Contents"), never a
     * raw column name.
     *
     * **Do not tell them to reload.** The controller redirects back, so the page
     * carrying this alert has already been re-rendered and its base hashes are
     * fresh — clicking again is all that is left to do, and the old "reload and
     * try again" described a step the app had already taken.
     */
    public static function valueChangedElsewhere(string $field): self
    {
        return new self(__('":field" changed somewhere else while this page was open, so nothing was reverted. This page now shows the current version — click again if you still want to go back.', [
            'field' => Str::headline($field),
        ]));
    }
}
