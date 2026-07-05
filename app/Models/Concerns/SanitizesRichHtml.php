<?php

namespace App\Models\Concerns;

use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Sanitizes rich-HTML fields on write. Applied to every model that owns a rich-HTML
 * field (see App\Support\RichTextFields) so untrusted editor markup is cleaned by
 * HtmlSanitizer *before* it is persisted — on every write path, including the seeder
 * and tinker.
 *
 * A set-mutator (not a booted() hook) is the deliberate choke point here: mutators
 * still run under WithoutModelEvents, whereas model events do not. That is why the
 * DB can never hold unsafe HTML regardless of how the row was written.
 *
 * Every model using the trait has a `description` rich field, so the shared mutator
 * lives here. A model with an additional rich field (Scene.notes) adds its own
 * mutator that delegates to cleanRichHtml().
 */
trait SanitizesRichHtml
{
    /**
     * Sanitize the shared `description` rich-HTML field on write.
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $this->cleanRichHtml($value),
        );
    }

    /**
     * Run a rich-HTML value through the sanitizer. Null/empty is preserved as-is so a
     * nullable column keeps storing null rather than an empty string.
     */
    protected function cleanRichHtml(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return app(HtmlSanitizer::class)->clean($value);
    }
}
