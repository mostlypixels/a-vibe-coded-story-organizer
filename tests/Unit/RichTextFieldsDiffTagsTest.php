<?php

namespace Tests\Unit;

use App\Support\RichTextFields;
use Tests\TestCase;

/** Reserve `<ins>` and `<del>` for generated diffs. */
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
