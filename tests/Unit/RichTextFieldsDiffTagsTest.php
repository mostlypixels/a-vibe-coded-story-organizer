<?php

namespace Tests\Unit;

use App\Support\RichTextFields;
use Tests\TestCase;

/**
 * A one-assertion-each guard on an invariant the whole diff layer rests on:
 * `<ins>` and `<del>` belong to the diff, and nothing else may produce them.
 *
 * The editor's strikethrough is `<s>` ("no longer accurate"); `<del>` means
 * "removed between these two revisions". They are different statements. Because
 * the author allow-list has no `ins`/`del` in it, the sanitizer strips any an
 * author could paste — which is what lets App\Services\Diff\DiffHtmlRenderer
 * treat those tags as unambiguously its own.
 *
 * This is cheap to keep and expensive to lose: adding `del` to ALLOWED_TAGS in
 * some future editor work would silently let stored content forge change
 * markers, and nothing else in the suite would notice.
 */
class RichTextFieldsDiffTagsTest extends TestCase
{
    public function test_strikethrough_stays_available_to_authors_as_s(): void
    {
        $this->assertContains('s', RichTextFields::ALLOWED_TAGS);
    }

    public function test_the_diff_markers_are_not_author_tags(): void
    {
        $this->assertNotContains('ins', RichTextFields::ALLOWED_TAGS);
        $this->assertNotContains('del', RichTextFields::ALLOWED_TAGS);
    }
}
