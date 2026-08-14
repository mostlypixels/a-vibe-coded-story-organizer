<?php

namespace Tests\Unit\Import;

use App\Services\Import\ManuskriptFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit-level tests for ManuskriptFile: the header+body split shared by every
 * Manuskript source file, with no domain knowledge of what a key means.
 */
class ManuskriptFileTest extends TestCase
{
    private string $scratchDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratchDirectory = sys_get_temp_dir().'/manuskript-file-test-'.uniqid();
        mkdir($this->scratchDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratchDirectory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->scratchDirectory);

        parent::tearDown();
    }

    private function writeFixture(string $name, string $contents): string
    {
        $path = $this->scratchDirectory.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_continuation_lines_are_joined_with_newline(): void
    {
        $path = $this->writeFixture('continuation.txt',
            "Summary:        First line\n".
            "                Second line\n".
            "Title:          A title\n"
        );

        $file = new ManuskriptFile($path);

        $this->assertSame("First line\nSecond line", $file->headerValue('Summary'));
        $this->assertSame('A title', $file->headerValue('Title'));
    }

    public function test_header_only_file_has_no_body(): void
    {
        $path = $this->writeFixture('header-only.txt', "Title: A chapter\nID: 3\n");

        $file = new ManuskriptFile($path);

        $this->assertSame('A chapter', $file->headerValue('Title'));
        $this->assertSame('', $file->body());
    }

    public function test_body_only_file_starting_with_a_blank_line_has_no_header(): void
    {
        $path = $this->writeFixture('body-only.md', "\nJust prose, no header at all.\n");

        $file = new ManuskriptFile($path);

        $this->assertSame([], $file->header());
        $this->assertSame("Just prose, no header at all.\n", $file->body());
    }

    public function test_a_header_shaped_line_inside_the_body_stays_in_the_body(): void
    {
        $path = $this->writeFixture('scene.md',
            "Title: A scene\n".
            "\n".
            "Some prose.\n".
            "Note: something the character said, not a header.\n"
        );

        $file = new ManuskriptFile($path);

        $this->assertSame('A scene', $file->headerValue('Title'));
        $this->assertSame(
            "Some prose.\nNote: something the character said, not a header.\n",
            $file->body()
        );
    }

    public function test_crlf_line_endings_are_normalized_to_lf(): void
    {
        $path = $this->writeFixture('crlf.md', "Title: A scene\r\n\r\nLine one\r\nLine two\r\n");

        $file = new ManuskriptFile($path);

        $this->assertSame('A scene', $file->headerValue('Title'));
        $this->assertSame("Line one\nLine two\n", $file->body());
    }

    public function test_utf8_accents_are_preserved(): void
    {
        $path = $this->writeFixture('accents.txt', "Name: Ren\u{e9}e Dupr\u{e9}\n\nElle habite \u{e0} c\u{f4}t\u{e9}.\n");

        $file = new ManuskriptFile($path);

        $this->assertSame("Ren\u{e9}e Dupr\u{e9}", $file->headerValue('Name'));
        $this->assertSame("Elle habite \u{e0} c\u{f4}t\u{e9}.\n", $file->body());
    }

    public function test_header_keys_are_matched_case_insensitively(): void
    {
        $path = $this->writeFixture('mixed-case.txt', "title:   A scene\n");

        $file = new ManuskriptFile($path);

        $this->assertSame('A scene', $file->headerValue('TITLE'));
        $this->assertSame('A scene', $file->headerValue('  Title  '));
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(RuntimeException::class);

        new ManuskriptFile($this->scratchDirectory.'/does-not-exist.txt');
    }
}
