<?php

namespace Tests\Unit\Support;

use App\Support\LocaleChoice;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * LocaleChoice::resolve() is the only entry point from a stored `users.locale`
 * to a locale — a stale or missing slug must fall back, never throw.
 */
class LocaleChoiceTest extends TestCase
{
    public function test_resolve_null_falls_back_to_the_config_default(): void
    {
        $choice = LocaleChoice::resolve(null);

        $this->assertSame(config('locales.default'), $choice->slug);
    }

    public function test_a_slug_removed_from_config_falls_back_to_the_default_instead_of_throwing(): void
    {
        $choice = LocaleChoice::resolve('zz');

        $this->assertSame(config('locales.default'), $choice->slug);
    }

    public function test_a_valid_slug_resolves_to_its_own_name_and_carbon_code(): void
    {
        $choice = LocaleChoice::resolve('fr');

        $this->assertSame('fr', $choice->slug);
        $this->assertSame(config('locales.supported.fr.name'), $choice->name);
        $this->assertSame(config('locales.supported.fr.carbon'), $choice->carbon);
    }

    public function test_from_slug_throws_on_an_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocaleChoice::fromSlug('no-such-locale');
    }

    public function test_all_is_keyed_by_every_configured_slug(): void
    {
        $choices = LocaleChoice::all();

        $this->assertSame(array_keys(config('locales.supported')), array_keys($choices));

        foreach ($choices as $slug => $choice) {
            $this->assertSame($slug, $choice->slug);
        }
    }
}
