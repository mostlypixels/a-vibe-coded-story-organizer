<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * An entity's age at a moment, in whole years.
 *
 * The single home for the age format ({@see CodexEntry::ageAt()}) — a later
 * add of finer precision changes only this class.
 */
final readonly class Age
{
    public function __construct(
        public int $years,
    ) {}

    /**
     * Whole years between inception and the moment, floored (no birthday yet
     * this year does not round up).
     */
    public static function between(CarbonInterface $inception, CarbonInterface $moment): self
    {
        return new self($inception->diffInYears($moment));
    }
}
