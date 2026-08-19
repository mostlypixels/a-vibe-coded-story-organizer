<?php

namespace App\Enums;

use App\Services\HtmlSanitizer;
use App\Support\RichTextFields;

/**
 * Selects which sanitizer allow-list {@see HtmlSanitizer} applies.
 *
 * Rich fields can carry the decorative classes of
 * {@see RichTextFields::decorativeClasses()}. Author Markdown
 * cannot: scene text becomes EPUB body and is read aloud, so it stays
 * structural. Structural removes every class.
 */
enum RichTextProfile
{
    case Rich;
    case Structural;
}
