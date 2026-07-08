<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Application-wide crawler policy — a singleton.
 *
 * Exactly one row ever exists: the whole site shares one robots.txt / noindex
 * policy. Unlike every other domain model this one is global (no owning Project
 * or User), so it is deliberately outside ProjectPolicy's authorization walk —
 * any authenticated user may edit it. See documentation/architecture.md.
 *
 * Always read the singleton through {@see self::current()}; never `new` a second
 * row.
 */
class CrawlerSetting extends Model
{
    protected $fillable = [
        'enabled',
        'user_agent_whitelist',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'user_agent_whitelist' => 'array',
        ];
    }

    /**
     * Return the singleton row, lazily creating it from config defaults on first
     * read (a fresh install has no row yet, but must still behave as hidden).
     *
     * Deliberately NOT memoised: the value can change within a single request
     * lifecycle (e.g. a settings update followed by a robots.txt fetch in one
     * test), and the single-row query is trivial.
     */
    public static function current(): self
    {
        return static::firstOr(fn () => static::create([
            'enabled' => config('crawlers.default_enabled'),
            'user_agent_whitelist' => [],
        ]));
    }

    /**
     * Whether the site is currently hidden from crawlers.
     */
    public function isHidden(): bool
    {
        return $this->enabled;
    }

    /**
     * The whitelist as a clean list: trimmed, with blank/whitespace entries
     * dropped so callers never emit an empty "User-agent:" line. Order preserved.
     *
     * @return array<int, string>
     */
    public function whitelistTerms(): array
    {
        return collect($this->user_agent_whitelist ?? [])
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->values()
            ->all();
    }
}
