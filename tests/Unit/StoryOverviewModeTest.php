<?php

namespace Tests\Unit;

use App\Enums\StoryOverviewMode;
use PHPUnit\Framework\TestCase;

class StoryOverviewModeTest extends TestCase
{
    public function test_labels(): void
    {
        $this->assertSame('One chapter per page', StoryOverviewMode::Chapter->label());
        $this->assertSame('Whole book', StoryOverviewMode::Whole->label());
    }

    public function test_default_is_chapter(): void
    {
        $this->assertSame(StoryOverviewMode::Chapter, StoryOverviewMode::default());
    }
}
