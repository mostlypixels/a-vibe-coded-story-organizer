<?php

namespace App\Support;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * The acts, chapters and scenes of one book, loaded once for the Write page.
 *
 * The page breadcrumb, the New scene dialog and the outline tree all read it,
 * so the page loads the tree with three queries and not once per reader.
 * It never filters the tree: story numbers depend on every row.
 *
 * A read-only lookup like {@see SceneNeighbours}, so it lives in app/Support.
 */
final class EditorOutline
{
    /**
     * @param  Collection<int, Act>  $acts
     */
    private function __construct(private Collection $acts, private StoryNumbering $numbering) {}

    public static function forBook(Book $book): self
    {
        $acts = $book->acts()
            ->with(['chapters' => function (Relation $query) {
                $query->orderBy('position')->orderBy('id')
                    ->with(['scenes' => function (Relation $query) {
                        $query->select(['id', 'chapter_id', 'name', 'position'])
                            ->orderBy('position')->orderBy('id');
                    }]);
            }])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return new self($acts, StoryNumbering::fromActs($acts));
    }

    /** @return Collection<int, Act> Acts with sorted chapters, with sorted scenes. */
    public function acts(): Collection
    {
        return $this->acts;
    }

    /** @return Collection<int, Chapter> Every chapter in story order, with the `act` relation set. */
    public function chapters(): Collection
    {
        return $this->acts
            ->flatMap(fn (Act $act) => $act->chapters->each(fn (Chapter $chapter) => $chapter->setRelation('act', $act)))
            ->values();
    }

    public function numbering(): StoryNumbering
    {
        return $this->numbering;
    }

    public function hasSingleAct(): bool
    {
        return $this->acts->count() === 1;
    }
}
