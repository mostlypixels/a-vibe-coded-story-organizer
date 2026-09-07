<?php

namespace Tests\Unit;

use App\Support\PageSize;
use Tests\TestCase;

class PageSizeTest extends TestCase
{
    public function test_sizes_returns_the_configured_allow_list(): void
    {
        $this->assertSame(config('pagination.sizes'), PageSize::sizes());
    }

    public function test_resolve_returns_the_default_for_null(): void
    {
        $this->assertSame(config('pagination.default'), PageSize::resolve(null));
    }

    public function test_resolve_returns_each_allowed_size_unchanged(): void
    {
        foreach (PageSize::sizes() as $size) {
            $this->assertSame($size, PageSize::resolve($size));
        }
    }

    public function test_resolve_returns_the_default_for_an_unlisted_size(): void
    {
        $this->assertSame(config('pagination.default'), PageSize::resolve(37));
    }

    public function test_resolve_returns_the_default_for_zero(): void
    {
        $this->assertSame(config('pagination.default'), PageSize::resolve(0));
    }
}
