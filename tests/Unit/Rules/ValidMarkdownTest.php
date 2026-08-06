<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidMarkdown;
use Tests\TestCase;

/**
 * Unit tests for the Markdown validation rule used by Scene::contents (and other
 * Markdown-mode rich-text fields). The rule uses GithubFlavoredMarkdownConverter,
 * not a bare CommonMarkConverter, so validation recognizes the same grammar
 * Scene::renderedContents() renders through Str::markdown() (GFM by default).
 *
 * Bare CommonMark never *rejects* strikethrough or task-list markup — tildes and
 * `[ ]` are inert text to it. The point is that validation means what the writer
 * expects downstream.
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
