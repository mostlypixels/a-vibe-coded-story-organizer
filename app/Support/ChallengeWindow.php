<?php

namespace App\Support;

use App\Enums\ChallengeRecurrence;
use App\Enums\ChallengeState;
use App\Models\Challenge;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The date range a challenge is judged against, and where that range sits
 * relative to a given day.
 *
 * A `None` challenge's window is `starts_on`…`ends_on`, unchanged. A
 * `Monthly` challenge's window is the calendar month containing `$today` —
 * never clipped to `starts_on`, so a challenge started on the 10th reads
 * behind par for that month rather than pro-rated. `starts_on` still in a
 * future month makes the window that future month, `Upcoming`. An `ends_on`
 * whose month has passed makes the window that last month, `Finished`.
 *
 * `$today` is always the caller's concern — pass in `WriterDay::for($user)`,
 * never resolve it here.
 *
 * > [!WARNING]
 * > All arithmetic runs on the calendar date only, anchored in UTC, so a
 * > month boundary or a day count is never off by the clock-shift hour of a
 * > DST change in the writer's own timezone.
 */
final readonly class ChallengeWindow
{
    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function for(Challenge $challenge, CarbonImmutable $today): self
    {
        $today = self::calendarDate($today);

        if ($challenge->recurrence === ChallengeRecurrence::None) {
            return new self(
                self::calendarDate($challenge->starts_on),
                self::calendarDate($challenge->ends_on),
            );
        }

        $starts = self::calendarDate($challenge->starts_on);
        $anchor = $starts->greaterThan($today) ? $starts : $today;

        if ($challenge->ends_on !== null) {
            $endsMonth = self::calendarDate($challenge->ends_on)->startOfMonth();
            if ($endsMonth->lessThan($anchor->startOfMonth())) {
                $anchor = $endsMonth;
            }
        }

        return new self($anchor->startOfMonth(), $anchor->endOfMonth()->startOfDay());
    }

    /**
     * Where the window sits relative to `$today`. Running includes the last
     * day; Finished starts the day after.
     */
    public function state(CarbonImmutable $today): ChallengeState
    {
        $today = self::calendarDate($today);

        return match (true) {
            $today->lessThan($this->from) => ChallengeState::Upcoming,
            $today->greaterThan($this->to) => ChallengeState::Finished,
            default => ChallengeState::Running,
        };
    }

    /**
     * Inclusive day count of the window.
     */
    public function totalDays(): int
    {
        return $this->from->diffInDays($this->to) + 1;
    }

    /**
     * Days from the first day of the window through `$today`, inclusive,
     * clamped to the window: 0 before it opens, `totalDays()` after it ends.
     *
     * The clamp lives here because the calendar-date anchoring does too.
     */
    public function elapsedDays(CarbonImmutable $today): int
    {
        $today = self::calendarDate($today);

        if ($today->lessThan($this->from)) {
            return 0;
        }

        return min($this->from->diffInDays($today) + 1, $this->totalDays());
    }

    private static function calendarDate(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date->format('Y-m-d'), 'UTC')->startOfDay();
    }
}
