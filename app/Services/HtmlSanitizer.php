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
        // HTMLPurifier's Forms module (label/input/textarea/button/...) is unsafe by
        // default and refuses to output any of its elements unless explicitly
        // enabled — needed for the task-list checkbox markup (<label>/<input
        // type="checkbox">) TipTap's TaskItem renders. HTML.Allowed above still gates
        // the final tag set, so this does NOT open up <form>/<textarea>/<button>/etc:
        // those stay excluded because they're absent from RichTextFields::ALLOWED_TAGS.
        $config->set('HTML.Forms', true);
        // Strip empty elements the editor may leave behind (e.g. an emptied <p>).
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Cache.SerializerPath', $cachePath);

        // HTMLPurifier's built-in HTML modules model HTML4/XHTML1, which predates
        // `data-*` attributes — it rejects them unless individually registered on
        // the raw definition below. Only the `data-*` names actually need this;
        // every other attribute in ALLOWED_ATTRIBUTES (href, src, type, ...) is
        // already a recognized HTML4 attribute. DefinitionID/Rev key the on-disk
        // cache set above: bump Rev whenever the registered attribute set changes,
        // or a stale cached definition will keep serving the old set.
        $config->set('HTML.DefinitionID', 'imagoldfish-rich-text');
        $config->set('HTML.DefinitionRev', 1);

        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            foreach (RichTextFields::ALLOWED_ATTRIBUTES as $tag => $attributes) {
                foreach ($attributes as $attribute) {
                    if (str_starts_with($attribute, 'data-')) {
                        $definition->addAttribute($tag, $attribute, 'Text');
                    }
                }
            }
        }

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
