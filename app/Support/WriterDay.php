<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The only place a writer's local date is derived.
 *
 * A writer keeps one working day across all their projects, from
 * `$user->timezone`. `null` falls back to `config('app.timezone')`. The
 * snapshot recorder, the chart's default range, and "is today's row already
 * there?" all call here, so they cannot drift against each other.
 *
 * > [!WARNING]
 * > Always resolve from the resource's owner, never `auth()->user()`. They
 * > are the same person today, but an import or an artisan command would
 * > silently file rows under the wrong day otherwise.
 */
class WriterDay
{
    /**
     * Start of the user's current local day.
     */
    public static function for(User $user): CarbonImmutable
    {
        return CarbonImmutable::now($user->timezone ?? config('app.timezone'))->startOfDay();
    }

    /**
     * The user's current local date, as `recorded_on` stores it.
     */
    public static function dateFor(User $user): string
    {
        return static::for($user)->toDateString();
    }
}
