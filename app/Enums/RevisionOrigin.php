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
}
