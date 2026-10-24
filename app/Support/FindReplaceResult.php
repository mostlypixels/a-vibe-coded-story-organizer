<?php

namespace App\Support;

/**
 * What a replace did: the matches changed, the scenes saved, and the scenes it skipped.
 *
 * A scene is skipped when its text changed after the preview. The author can
 * preview again and decide.
 */
final readonly class FindReplaceResult
{
    /**
     * @param  list<array{id: int, name: string}>  $skipped
     */
    public function __construct(
        public int $replaced = 0,
        public int $scenesChanged = 0,
        public array $skipped = [],
    ) {}

    /**
     * A plain array, so the session can carry it to the next page.
     *
     * @return array{replaced: int, scenes: int, skipped: list<array{id: int, name: string}>}
     */
    public function toArray(): array
    {
        return [
            'replaced' => $this->replaced,
            'scenes' => $this->scenesChanged,
            'skipped' => $this->skipped,
        ];
    }
}
