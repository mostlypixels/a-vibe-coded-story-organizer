<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidMarkdown;
use Tests\TestCase;

/** Validate the same GitHub-flavored Markdown that the app renders. */
class ValidMarkdownTest extends TestCase
{
    /** @return array{bool, ?string} */
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
