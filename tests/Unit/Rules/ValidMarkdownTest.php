<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidMarkdown;
use Tests\TestCase;

/**
 * Unit tests for the Markdown validation rule used by Scene::contents (and other
 * Markdown-mode rich-text fields). Task 02 of the expand-tip-tap plan switched this
 * rule's converter from a bare CommonMarkConverter to GithubFlavoredMarkdownConverter
 * so validation recognizes the same grammar Scene::renderedContents() already renders
 * via Str::markdown() (GFM by default). Note: strikethrough/task-list markup was never
 * *rejected* by the old converter — tildes and `[ ]` were just inert text to bare
 * CommonMark, so this fix is about validation meaning what the writer expects
 * downstream, not about newly-passing syntax.
 */
class ValidMarkdownTest extends TestCase
{
    /**
     * Run the rule and capture whether it failed and the message it reported.
     *
     * @return array{bool, ?string}
     */
    private function validate(mixed $value): array
    {
        $failed = false;
        $message = null;

        (new ValidMarkdown)->validate('contents', $value, function (string $reason) use (&$failed, &$message) {
            $failed = true;
            $message = $reason;
        });

        return [$failed, $message];
    }

    public function test_plain_markdown_passes(): void
    {
        [$failed] = $this->validate("# Heading\n\nSome *text*.");

        $this->assertFalse($failed);
    }

    public function test_strikethrough_syntax_passes(): void
    {
        [$failed] = $this->validate('This is ~~struck~~ text.');

        $this->assertFalse($failed);
    }

    public function test_gfm_task_list_syntax_passes(): void
    {
        [$failed] = $this->validate("- [ ] todo\n- [x] done");

        $this->assertFalse($failed);
    }
}
