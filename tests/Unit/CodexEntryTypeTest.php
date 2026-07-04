<?php

namespace Tests\Unit;

use App\Enums\CodexEntryType;
use PHPUnit\Framework\TestCase;

class CodexEntryTypeTest extends TestCase
{
    public function test_route_keys_returns_every_type_key_in_order(): void
    {
        $this->assertSame(
            ['characters', 'locations', 'organizations'],
            CodexEntryType::routeKeys()
        );
    }

    public function test_from_route_key_resolves_each_key(): void
    {
        $this->assertSame(CodexEntryType::Character, CodexEntryType::fromRouteKey('characters'));
        $this->assertSame(CodexEntryType::Location, CodexEntryType::fromRouteKey('locations'));
        $this->assertSame(CodexEntryType::Organization, CodexEntryType::fromRouteKey('organizations'));
    }

    public function test_from_route_key_rejects_an_unknown_key(): void
    {
        $this->expectException(\ValueError::class);

        CodexEntryType::fromRouteKey('dragons');
    }
}
