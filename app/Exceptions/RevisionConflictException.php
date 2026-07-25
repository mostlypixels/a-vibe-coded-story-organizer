<?php

namespace App\Exceptions;

use App\Services\RevisionReverter;
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
     */
    public static function valueChangedElsewhere(): self
    {
        return new self(__('This changed somewhere else since you opened this page — reload and try again.'));
    }
}
