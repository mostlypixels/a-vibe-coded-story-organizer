# 01 — Link target type

## Scope

- Rename `App\Enums\NoteLinkType` → `App\Enums\LinkTargetType` and update every user: controllers, requests, the `NoteLinkTarget` rule, `HasNotes`, `Note`, the importer, `StaticSiteExporter`, views, tests, docs.
- Add case `Note = 'note'` with its model, label column and `queryFor($project)`.
- Add `noteLinkable(): bool`, false for `Note`. The note-link picker, `StoreNoteLinkRequest` and the archive note-link validation use it.
- Deferred: everything about `entity:` links → 02 onwards.

## Depends on

- None.

## Key decisions already made

- Values do not change. No migration for `notables` or archives.
- Note-to-note links are text links only. A structured note → note link stays invalid: 422 on store, import error in an archive.

## Docs

- `expanded/data-model.md` → Target type enum.

## Tests

- Rename `NoteLinkTypeTest` → `LinkTargetTypeTest`. Assert the archive values are unchanged and `note` exists.
- `NoteLinkControllerTest`: storing a `note` link gives 422; the candidates endpoint rejects `type=note`.
- Archive import: a note link of type `note` is an import error.
