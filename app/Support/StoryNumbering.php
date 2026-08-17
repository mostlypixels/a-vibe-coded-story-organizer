<?php

namespace App\Support;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Continuous, book-wide display numbers for acts, chapters and scenes.
 *
 * `position` is a per-parent, gappy sort key (the only thing move up/down
 * writes); `number` is its opposite — a book-wide, gap-free rank computed
 * at read time, never stored. This class is the single place that derivation
 * happens. See documentation/architecture.md -> "Continuous numbering".
 *
 * Numbering restarts at each book: Act 1 of book 2 is Act 1, and its
 * chapters and scenes restart too — a reader of book 2 counts from one.
 *
 * A read-only lookup table, not a workflow, so it lives in app/Support next
 * to PlotlineColors rather than app/Services.
 */
final class StoryNumbering
{
    /** @var array<int, int> act id => 1-based book-wide number */
    private array $actNumbers;

    /** @var array<int, int> chapter id => 1-based book-wide number */
    private array $chapterNumbers;

    /** @var array<int, int> scene id => 1-based book-wide number */
    private array $sceneNumbers;

    /**
     * @param  array<int, int>  $actNumbers
     * @param  array<int, int>  $chapterNumbers
     * @param  array<int, int>  $sceneNumbers
     */
    private function __construct(array $actNumbers, array $chapterNumbers, array $sceneNumbers)
    {
        $this->actNumbers = $actNumbers;
        $this->chapterNumbers = $chapterNumbers;
        $this->sceneNumbers = $sceneNumbers;
    }

    /**
     * Load the minimal id/position tree for $book and derive its numbering.
     * Fires one query per level (acts, chapters, scenes) through eager
     * loading. Use {@see fromActs()} instead when the caller already holds
     * the full tree, to avoid loading it twice.
     */
    public static function forBook(Book $book): self
    {
        $acts = $book->acts()
            ->select(['id', 'position'])
            ->with(['chapters' => function (HasMany $query) {
                $query->select(['id', 'act_id', 'position'])
                    ->with(['scenes' => function (HasMany $query) {
                        $query->select(['id', 'chapter_id', 'position']);
                    }]);
            }])
            ->get();

        return self::fromActs($acts);
    }

    /**
     * Derive numbering from an already-loaded tree. Fires no queries.
     *
     * $acts must be every act (with its chapters, with their scenes) in the
     * book — a filtered or paginated subset would compact the numbering
     * around the missing rows, renumbering siblings that never moved.
     *
     * Each level is re-sorted here by (position, id) before ranking, so a
     * caller's eager-load order is not load-bearing: `position` has no unique
     * constraint, and two siblings sharing one must still number
     * deterministically.
     */
    public static function fromActs(Collection $acts): self
    {
        $actNumbers = [];
        $chapterNumbers = [];
        $sceneNumbers = [];

        $chapterNumber = 0;
        $sceneNumber = 0;

        foreach (self::sortByPositionThenId($acts) as $actIndex => $act) {
            $actNumbers[$act->id] = $actIndex + 1;

            foreach (self::sortByPositionThenId($act->chapters) as $chapter) {
                $chapterNumbers[$chapter->id] = ++$chapterNumber;

                foreach (self::sortByPositionThenId($chapter->scenes) as $scene) {
                    $sceneNumbers[$scene->id] = ++$sceneNumber;
                }
            }
        }

        return new self($actNumbers, $chapterNumbers, $sceneNumbers);
    }

    /**
     * The book-wide, 1-based number for this act.
     */
    public function act(Act|int $act): int
    {
        return $this->lookup($this->actNumbers, $act, 'act');
    }

    /**
     * The book-wide, 1-based number for this chapter — continuous across
     * every act boundary.
     */
    public function chapter(Chapter|int $chapter): int
    {
        return $this->lookup($this->chapterNumbers, $chapter, 'chapter');
    }

    /**
     * The book-wide, 1-based number for this scene — continuous across
     * every chapter and act boundary.
     */
    public function scene(Scene|int $scene): int
    {
        return $this->lookup($this->sceneNumbers, $scene, 'scene');
    }

    /**
     * @param  array<int, int>  $numbers
     */
    private function lookup(array $numbers, Act|Chapter|Scene|int $model, string $label): int
    {
        $id = is_int($model) ? $model : $model->id;

        if (! array_key_exists($id, $numbers)) {
            throw new RuntimeException("Unknown {$label} id [{$id}]: no story number was derived for it.");
        }

        return $numbers[$id];
    }

    /**
     * Sort a collection of siblings by (position, id) — the tie-break every
     * numbering level shares.
     */
    private static function sortByPositionThenId(Collection $models): Collection
    {
        return $models
            ->sortBy([
                ['position', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
