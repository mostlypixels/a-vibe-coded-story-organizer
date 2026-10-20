# 01 — Editor outline data

## Scope

- New `App\Support\EditorOutline`: loads acts → chapters → scenes of one book, holds them and a `StoryNumbering::fromActs()`.
- `EditorController::show` passes `outline`. `$context` uses its numbering, in place of `StoryNumbering::forBook()`.
- New scene dialog `$chapters` come from the outline, in place of `chaptersInStoryOrder()`.
- No visible change. The tree partial is task 03.

## Depends on

- None.

## Key decisions already made

- Contract (bodies are the implementer's):

  ```php
  final class EditorOutline
  {
      public static function forBook(Book $book): self;
      /** @return Collection<int, Act> acts with sorted chapters, with sorted scenes */
      public function acts(): Collection;
      /** @return Collection<int, Chapter> every chapter in story order, `act` relation set */
      public function chapters(): Collection;
      public function numbering(): StoryNumbering;
      public function hasSingleAct(): bool;
  }
  ```

- Scene columns: `id`, `chapter_id`, `name`, `position`, `word_count`.
- `chapters()` sets the `act` relation on each chapter, so the dialog's `$chapter->act->name` fires no query.
- `app/Support`, not `app/Services`: a read-only lookup like `SceneNeighbours`.

## Docs

- `expanded/architecture.md` → Outline data.

## Tests

- Unit: siblings with equal `position` sort by `id`; `hasSingleAct()`; `chapters()` order crosses acts.
- Feature (`EditorTest`): breadcrumb and dialog chapter list unchanged; query count with 3 scenes equals 30 scenes.
