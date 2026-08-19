<?php

namespace App\Support;

use App\Support\Markdown\StrikethroughSExtension;
use Illuminate\Support\Str;

/**
 * The one renderer for author-written Markdown (`Scene.contents` and Markdown
 * revisions). Wraps `Str::markdown()` so every caller gets the same GitHub
 * flavour *and* the same strikethrough tag.
 *
 * EpubExporter keeps its own converter on purpose — it adds SmartPunct, which
 * must not reach the shared renderer — and registers the same extension there.
 */
class AuthorMarkdown
{
    public static function render(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [], [new StrikethroughSExtension]);
    }
}
