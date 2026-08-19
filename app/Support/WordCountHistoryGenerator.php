<?php

namespace App\Support;

use Closure;

/**
 * A plausible writing history for a demo project: gaps, one cut day, and
 * daily figures that swing above and below a typical target — ending on
 * exactly the project's real total.
 *
 * Deterministic: the same `$total`/`$days`/`$seed` always produce the same
 * plan, so `DatabaseSeederTest` can assert on exact numbers even though
 * paratest gives every test process its own database.
 *
 * > [!WARNING]
 * > Only ever generate history for a fictional project (Melusine). The rule
 * > against inventing a writer's history protects real ones — see
 * > `documentation/features/writing-progress.md`.
 */
final class WordCountHistoryGenerator
{
    /** Rest days get no row: the read path must fill the gap, not this class. */
    private const MIN_REST_DAYS = 2;

    private const MAX_EXTRA_REST_DAY = 1;

    /**
     * @return list<array{offset: int, word_count: int}> one entry per day that
     *                                                   gets a row. `offset` counts from the oldest day (0) to today (`$days` -
     *                                                   1). An offset with no entry is a rest day.
     */
    public static function plan(int $total, int $days, int $seed): array
    {
        $random = self::sequence($seed);

        $restDays = self::restDayOffsets($days, self::MIN_REST_DAYS + ($random() < 0.5 ? 0 : self::MAX_EXTRA_REST_DAY), $random);
        $writingOffsets = array_values(array_diff(range(0, $days - 1), $restDays));

        $deltas = self::scaleToTotal(self::weights($writingOffsets, $random), $total);

        $cumulative = 0;
        $plan = [];

        foreach ($writingOffsets as $index => $offset) {
            $cumulative += $deltas[$index];
            $plan[] = ['offset' => $offset, 'word_count' => $cumulative];
        }

        return $plan;
    }

    /**
     * A deterministic float in `[0, 1)` per call. xorshift32 rather than
     * `mt_srand()`: that reseeds PHP's shared global generator, which would
     * change output for anything else drawing from it in the same process
     * (Faker included) instead of staying local to this one plan.
     */
    private static function sequence(int $seed): Closure
    {
        $state = $seed === 0 ? 1 : $seed & 0xFFFFFFFF;

        return function () use (&$state): float {
            $state ^= ($state << 13) & 0xFFFFFFFF;
            $state ^= ($state >> 17);
            $state ^= ($state << 5) & 0xFFFFFFFF;
            $state &= 0xFFFFFFFF;

            return $state / 0xFFFFFFFF;
        };
    }

    /**
     * @return list<int> distinct offsets, never the first day or today. The
     *                   first writing day must already carry the run's opening total, and the
     *                   last row must land on today.
     */
    private static function restDayOffsets(int $days, int $count, Closure $random): array
    {
        $candidates = range(1, $days - 2);
        $offsets = [];

        while (count($offsets) < $count && $candidates !== []) {
            $index = (int) floor($random() * count($candidates));
            $offsets[] = $candidates[$index];
            unset($candidates[$index]);
            $candidates = array_values($candidates);
        }

        return $offsets;
    }

    /**
     * One weight per writing day, `0.4`-`2.2` of the eventual average delta —
     * wide enough that a goal set near that average sits below some days and
     * above others.
     *
     * The third writing day is always forced negative rather than left to
     * chance: "at least one negative day" must hold on every run, not most of
     * them. Its size is capped small so two prior writing days are enough to
     * absorb it without the running total going negative.
     *
     * @param  list<int>  $writingOffsets
     * @return list<float>
     */
    private static function weights(array $writingOffsets, Closure $random): array
    {
        $weights = [];

        foreach ($writingOffsets as $offset) {
            $weights[] = 0.4 + $random() * 1.8;
        }

        $weights[min(2, count($weights) - 1)] = -(0.1 + $random() * 0.2);

        return $weights;
    }

    /**
     * Scale weights so they sum to exactly `$total`, remainder on the last
     * day. The chart's final point must equal the live `SUM`, not land close
     * to it.
     *
     * @param  list<float>  $weights
     * @return list<int>
     */
    private static function scaleToTotal(array $weights, int $total): array
    {
        $rawSum = array_sum($weights);
        $scale = $rawSum > 0 ? $total / $rawSum : 0;

        $deltas = array_map(static fn (float $weight): int => (int) round($weight * $scale), $weights);

        $deltas[array_key_last($deltas)] += $total - array_sum($deltas);

        return $deltas;
    }
}
