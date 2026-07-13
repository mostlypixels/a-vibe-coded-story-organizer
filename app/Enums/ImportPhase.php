<?php

namespace App\Enums;

/**
 * The lifecycle phases of a project import, in dependency order.
 *
 * `phase` on an `Import` row records the LAST successfully completed phase;
 * resuming a stalled import starts at the next one. The four graph-import
 * phases (Project → Timeline → Story → Codex) each commit as their own
 * transaction, checkpointed onto the Import row so a crash mid-import is
 * recoverable.
 *
 * `Completed` is the terminal success state. `Failed` is the terminal error
 * state, reserved for ArchiveValidator/ContentSanitizer rejections that never
 * reach phase 1 — a stalled mid-import just sits at its last completed
 * non-terminal phase, it is NOT `Failed`.
 */
enum ImportPhase: string
{
    case Pending = 'pending';
    case Project = 'project';
    case Timeline = 'timeline';
    case Story = 'story';
    case Codex = 'codex';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Human-readable label for the Import tab's status text (task 08).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Project => 'Project created',
            self::Timeline => 'Timeline imported',
            self::Story => 'Story imported',
            self::Codex => 'Codex imported',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
