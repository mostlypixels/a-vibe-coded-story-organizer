<?php

namespace App\Support;

/**
 * Every match of a find and replace, grouped by scene in manuscript order.
 */
final readonly class FindReplacePreview
{
    /** The scene groups start open when the whole preview has this many matches or fewer. */
    public const OPEN_LIMIT = 20;

    /**
     * @param  list<FindReplaceSceneMatches>  $scenes
     * @param  bool  $capped  true when more matches exist than the list holds
     */
    public function __construct(
        public array $scenes,
        public bool $capped = false,
    ) {}

    public function matchCount(): int
    {
        return array_sum(array_map(fn (FindReplaceSceneMatches $scene) => count($scene->matches), $this->scenes));
    }

    public function isEmpty(): bool
    {
        return $this->scenes === [];
    }

    /** With few matches the author can read all of them without unfolding. */
    public function startsOpen(): bool
    {
        return $this->matchCount() <= self::OPEN_LIMIT;
    }

    /**
     * Every match chosen, for a submit with no selection UI.
     *
     * @return array<int, array{hash: string, indexes: list<int>}> keyed by scene ID
     */
    public function selectAll(): array
    {
        $selection = [];

        foreach ($this->scenes as $group) {
            $selection[$group->scene->id] = [
                'hash' => $group->hash,
                'indexes' => array_map(fn (FindReplaceMatch $match) => $match->index, $group->matches),
            ];
        }

        return $selection;
    }
}
