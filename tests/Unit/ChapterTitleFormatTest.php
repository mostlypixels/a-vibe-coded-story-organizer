<?php

namespace Tests\Unit;

use App\Enums\ChapterTitleFormat;
use PHPUnit\Framework\TestCase;

class ChapterTitleFormatTest extends TestCase
{
    public function test_chapter_number_title_format(): void
    {
        $format = ChapterTitleFormat::ChapterNumberTitle;

        $this->assertSame('Chapter 12: The Storm', $format->format(12, 'The Storm'));
    }

    public function test_chapter_number_title_format_with_blank_name(): void
    {
        $format = ChapterTitleFormat::ChapterNumberTitle;

        $this->assertSame('Chapter 12', $format->format(12, ''));
        $this->assertSame('Chapter 12', $format->format(12, null));
        $this->assertSame('Chapter 12', $format->format(12, '   '));
    }

    public function test_number_title_format(): void
    {
        $format = ChapterTitleFormat::NumberTitle;

        $this->assertSame('12: The Storm', $format->format(12, 'The Storm'));
    }

    public function test_number_title_format_with_blank_name(): void
    {
        $format = ChapterTitleFormat::NumberTitle;

        $this->assertSame('12', $format->format(12, ''));
        $this->assertSame('12', $format->format(12, null));
        $this->assertSame('12', $format->format(12, '   '));
    }

    public function test_chapter_number_format(): void
    {
        $format = ChapterTitleFormat::ChapterNumber;

        $this->assertSame('Chapter 12', $format->format(12, 'The Storm'));
        $this->assertSame('Chapter 12', $format->format(12, ''));
        $this->assertSame('Chapter 12', $format->format(12, null));
    }

    public function test_number_format(): void
    {
        $format = ChapterTitleFormat::Number;

        $this->assertSame('12', $format->format(12, 'The Storm'));
        $this->assertSame('12', $format->format(12, ''));
        $this->assertSame('12', $format->format(12, null));
    }

    public function test_title_format(): void
    {
        $format = ChapterTitleFormat::Title;

        $this->assertSame('The Storm', $format->format(12, 'The Storm'));
    }

    public function test_title_format_with_blank_name(): void
    {
        $format = ChapterTitleFormat::Title;

        $this->assertSame('', $format->format(12, ''));
        $this->assertSame('', $format->format(12, null));
        $this->assertSame('', $format->format(12, '   '));
    }
}
