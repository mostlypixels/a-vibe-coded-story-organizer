<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * One locale, read from `config/locales.php`.
 *
 * Locales are config, not database rows: the offered set and each one's name
 * change only when someone edits a file. The single runtime-varying value is
 * which slug is active. Mirrors ThemePreset.
 *
 * Thin by design: no clock or segment-order fields. Display and the picker
 * derive those from `carbon`/ICU data instead of a hand-kept map.
 */
final readonly class LocaleChoice
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $carbon,
    ) {}

    /**
     * @throws InvalidArgumentException when no locale is configured under that slug —
     *                                  callers that accept user input must validate the
     *                                  slug against all() first
     */
    public static function fromSlug(string $slug): self
    {
        $locale = config("locales.supported.{$slug}");

        if (! is_array($locale)) {
            throw new InvalidArgumentException("Unknown locale: {$slug}");
        }

        return new self(
            slug: $slug,
            name: $locale['name'] ?? $slug,
            carbon: $locale['carbon'] ?? $slug,
        );
    }

    /**
     * The locale a stored `users.locale` resolves to, falling back to
     * `config('locales.default')` when the slug is `null` (never chosen) or no
     * longer matches a configured locale. The latter matters because a locale
     * can be removed from config after users already picked it — a stale
     * value in the column must not throw and white-screen every page.
     */
    public static function resolve(?string $slug): self
    {
        if ($slug === null || ! array_key_exists($slug, config('locales.supported', []))) {
            $slug = config('locales.default');
        }

        return self::fromSlug($slug);
    }

    /**
     * Every configured locale, keyed by slug — the picker's option list.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        $slugs = array_keys(config('locales.supported', []));

        return array_combine(
            $slugs,
            array_map(static fn (string $slug): self => self::fromSlug($slug), $slugs),
        );
    }
}
