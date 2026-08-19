# Revisions

[Documentation](../README.md) › [Features](README.md) › Revisions

## Storage and save points

The `revisions` table stores one immutable row for each field and moment. `save_id` groups rows into the save points shown to users.

- Autosave writes the live model column. There is no draft store.
- A field filter is `?field=` on an entity history page.
- `App\Services\RevisionHistory` folds rows into save points.
- List queries select metadata only. They do not hydrate revision values.

## Field registry

`App\Support\AutosavableFields::REGISTRY` is the source of truth for:

- entity route slugs;
- autosavable fields;
- `FieldKind`;
- validation rules;
- field resolution for autosave and history.

`config/revisions.php` stores coalescing windows and caps by `entity.field`. Tests require every config key to match the registry.

> [!WARNING]
> Form Requests must call the registry validation rule. A literal `max:` for an autosaved field can reject content that autosave already stored.

## Recording

`App\Services\RevisionRecorder` is the only revision writer.

- Automatic saves within the coalescing window update one open row.
- Manual, revert, and baseline origins always insert.
- A coalesced row keeps its original `save_id` and `created_at`.
- Callers skip byte-identical values.
- A manual save records only changed fields.
- The first change can create a baseline with the previous value and entity timestamp.
- Every row stores `project_id` explicitly so project cascades remain valid.

## Retention

Prune and purge have different safety rules.

| Operation | Scope |
| --- | --- |
| Prune | Old, automatic, unlabeled rows; never the newest row for a field |
| Purge | Explicit categories selected by the user |

Newest means the greatest `(created_at, id)`. `Revision::prunable()` uses a newer-sibling check instead of `MAX(id)` because backdated baselines can have a newer ID and an older timestamp.

> [!CAUTION]
> A retention change must prove that labeled rows, non-automatic rows, and the newest row for every field survive.

## Diffing and summaries

`App\Services\RevisionDiffer` selects a source or visual diff by `FieldKind`.

- Plain and Markdown fields use source diffs.
- Rich fields use sanitized visual HTML diffs.
- `RevisionSummarizer` produces short metadata without loading full values in list queries.
- Diff markup uses the shared `x-diff` component and theme tokens.

A rich field's block alignment and span colour (see
[Rich text](rich-text.md#decorative-classes)) are formatting, not text, so a change to only
one of them is reported the same way a bold or link change is: as a `FormattingChanged`
span rather than a delete-and-insert of the same words. Subscript, superscript and a task
item's tick are reported the same way.

> [!NOTE]
> `HtmlTokenizer` splits words on whitespace only, never at a tag boundary. A mark that
> starts mid-word — `mc<sup>2</sup>`, a bolded stem — would otherwise put the halves in two
> text nodes and make them two words, changing the block's text and `matchKey()` so the
> differ reported a delete plus an insert. A word spanning marks carries the union of them:
> half-bold reads as bold, which is the right altitude for a change summary.

- Alignment belongs to the block, not to any word inside it, so
  `App\Services\Diff\HtmlTokenizer` folds it into the block's signature as a pseudo-mark
  (`align:center`) instead of a per-word mark. Without this, a re-aligned paragraph would
  pair with itself and read as unchanged instead of reporting the change.
- A task item's tick is block-level in the same way, and folds into the signature as the
  pseudo-mark `checked`. The signature is what `VisualHtmlDiffer` compares to decide
  "unchanged", so an attribute it never reads reports nothing — which is why collecting
  `data-checked` in the tokenizer was not by itself enough.

> [!WARNING]
> Anything the differ should notice must reach the **signature**. `data-callout-type` and an
> image's `width`/`height` are still collected but never compared, so changing a callout's
> type or resizing an image reports no change.
- Colour is a per-word mark (`color:red`) like any other inline mark, because a span can
  colour part of a block.

## Revert and undo

`App\Services\RevisionReverter` writes through the normal model save path.

- Revert restores one field from a selected revision.
- Undo restores every field in one save point to its predecessor.
- Both actions record new `revert` rows. History remains append-only.
- Conflict checks compare the current value with the expected base.
- Word counts, sanitization, and other model invariants run during restoration.

## Entry points

- Per-field History icon.
- Entity History link.
- Project revisions browser under Tools.

Routes resolve entities through the registry and authorize through the owning project.

## Known limits

- Autosave conflict resolution is explicit. It does not merge text.
- A coalesced field can remain in an earlier save point than other fields from the same manual save.
- Revision values can be large. Keep list queries value-free.

## Related documentation

- [Architecture](../architecture/README.md)
- [Rich text](rich-text.md)
- [Writing progress](writing-progress.md)
