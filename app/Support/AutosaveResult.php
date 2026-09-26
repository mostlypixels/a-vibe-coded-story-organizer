<?php

namespace App\Support;

/** What one autosave stored. The client uses it as its next base. */
final class AutosaveResult
{
    public function __construct(
        public readonly string $value,
        public readonly int $wordCount,
        public readonly ?int $revisionId,
        public readonly bool $referencesSynced = false,
    ) {}

    public function hash(): string
    {
        return FieldHash::of($this->value);
    }
}
