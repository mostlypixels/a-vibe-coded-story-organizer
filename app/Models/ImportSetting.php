<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Application-wide import policy — a singleton.
 *
 * Exactly one row ever exists: the whole application shares one import size cap
 * and default run mode. Like {@see CrawlerSetting} this model is global (no
 * owning Project or User), so it is deliberately outside ProjectPolicy's
 * authorization walk — any authenticated user may edit it (settings vs. the
 * per-attempt {@see Import} tracking record). See documentation/architecture.md.
 *
 * Always read the singleton through {@see self::current()}; never `new` a second
 * row.
 */
class ImportSetting extends Model
{
    protected $fillable = [
        'max_archive_kilobytes',
        'run_in_background',
    ];

    protected function casts(): array
    {
        return [
            'run_in_background' => 'boolean',
        ];
    }

    /**
     * Return the singleton row, lazily creating it from config defaults on first
     * read (a fresh install has no row yet, but must still have a working cap).
     *
     * Deliberately NOT memoised: the value can change within a single request
     * lifecycle (e.g. a settings update followed by an import in one test), and
     * the single-row query is trivial.
     */
    public static function current(): self
    {
        return static::firstOr(fn () => static::create([
            'max_archive_kilobytes' => config('import.default_max_archive_kilobytes'),
            'run_in_background' => config('import.default_run_in_background'),
        ]));
    }
}
