<?php

namespace App\Enums;

use App\Models\Revision;

/**
 * Why a given {@see Revision} row exists.
 *
 * Only {@see self::Automatic} revisions are ever eligible for pruning
 * (`Revision::prunable()`) — every other origin is a deliberate, user-visible
 * event (a manual save, a revert, an import, or the pre-edit baseline) and must
 * survive retention regardless of age. See .specs/.../handoff.md §4.2.
 */
enum RevisionOrigin: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Revert = 'revert';
    case Import = 'import';
    case Baseline = 'baseline';

    /**
     * The origin badge label shown on the history page (task 10,
     * expanded/ui.md "History page"). `Baseline` itself is never rendered
     * through this — that row gets its own dedicated "Baseline — value before
     * revision history" row instead (handoff.md §9.2) — but the label is kept
     * here alongside the others rather than leaving one case undocumented.
     */
    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Autosaved',
            self::Manual => 'Saved',
            self::Revert => 'Reverted',
            self::Import => 'Imported',
            self::Baseline => 'Baseline',
        };
    }
}
