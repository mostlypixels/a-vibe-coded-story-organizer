# 09 — "Linked from" card

## Scope

- Blade component `x-linked-from-card`: lists the `entity_links` rows that target an entity, with source title and field, linked to the source.
- Place it next to `x-notes-card` on the read page of every `LinkTargetType`, the note read page included.
- Hidden when empty. Eager-load the sources.
- Document it in `documentation/features/rich-text.md`, or in a new feature page linked from the features index.

## Depends on

- 04.

## Key decisions already made

- Every target type gets the card.
- The card shows sources from the same project only.

## Docs

- `expanded/ui.md` → Backlinks.

## Tests

- The card lists each source with a working link, and is hidden when there is none.
- Query count stays flat with many sources.
- A non-owner still gets 403 on the target page.
