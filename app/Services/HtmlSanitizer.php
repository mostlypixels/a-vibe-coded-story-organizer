<?php

namespace App\Services;

use App\Support\RichTextFields;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

/**
 * The single place editor markup is cleaned (alongside AttributeTimeline /
 * CodexMediaService in app/Services). Wraps HTMLPurifier with the strict
 * allow-list from RichTextFields.
 *
 * Client-side editor output is untrusted input: everything persisted to a
 * rich-HTML field passes through clean() first (task 02 wires the mutators), so
 * the DB never holds unsafe HTML regardless of entry path. Rendering with
 * {!! !!} downstream is then "intentionally rendering trusted HTML".
 */
class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $cachePath = storage_path('app/htmlpurifier');
        File::ensureDirectoryExists($cachePath);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', RichTextFields::purifierAllowedHtml());
        $config->set('URI.AllowedSchemes', RichTextFields::purifierAllowedSchemes());
        // Strip empty elements the editor may leave behind (e.g. an emptied <p>).
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Cache.SerializerPath', $cachePath);

        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Return the input HTML with everything outside the allow-list removed.
     */
    public function clean(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
