---
title: "Task 01 — Data model"
---

# Task 01 — Data model

## Scope

- Migrations for `note_categories`, `notes`, `notables` (columns and indexes in
  `expanded/data-model.md` → Tables).
- Models `Note`, `NoteCategory`, `Notable`; factories for `Note` and `NoteCategory`.
- `App\Enums\NoteLinkType` with the seven cases.
- `App\Models\Concerns\HasNotes` on Book, Act, Chapter, Scene, Event, Plotline, CodexEntry.
- `Notable::deleteFor()` calls in Book, Act and Chapter `deleting`.
- `Project::notes()`, `Project::noteCategories()`.

Defers: controllers and views (02+), registry entries (02), `scenes.notes` removal (09).

## Depends on

Nothing.

## Key decisions already made

- `notable_type` stores the model class name, like `revisions.revisionable_type`. No morph map.
- `NoteLinkType` contract, per case:

  ```php
  public function modelClass(): string;          // class-string<Model>
  public function label(): string;
  public function showRoute(): string;
  public function queryFor(Project $project): Builder; // rows of this type in the project
  public static function fromModel(Model $model): self;
  ```

- `Note` uses `HasRevisions` and `SanitizesRichHtml` now; its registry entry lands in 02.
  If `SanitizesRichHtml` needs the `RichTextFields` entry to work, add `Note::class => ['body']`
  here instead.
- `HasNotes` hook is `deleted`, not `deleting`, same reason as `HasRevisions`.

## Docs

`expanded/data-model.md` → Tables, Models, Linkable types, Invariants.

## Tests

- `NoteLifecycleTest`: every case in `testing.md` → Lifecycle.
- Unit test for `NoteLinkType::queryFor()`: returns only the given project's rows, for each case.
