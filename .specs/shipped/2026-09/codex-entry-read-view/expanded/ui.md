# UI

One new view, one new partial. `codex/partials/fields.blade.php` is the form and is not reused.

## `resources/views/codex/show.blade.php`

Order, top to bottom:

1. **Header** — cover thumbnail, name, type label, aliases as `x-badge`, tags as `x-badge`.
2. **Actions** — Edit (`codex.edit`), Duplicate (the existing `x-icon-dialog-button` modal from
   `codex/index.blade.php`), History (`revisions.index` with entity `codex`), Delete.
3. **Description** — rendered rich text, not `x-wysiwyg`.
4. **Attributes** — the new partial, set attributes only, each as its full timeline.
5. **Lifespan** — inception and termination events, when either is set.
6. **Referenced in scenes** — the scenes from `ReferencingScenes`, each linking to the scene,
   with its chapter and act.

Sections 4, 5 and 6 are omitted when empty rather than shown holding an em dash. An entry with
a name and nothing else renders as a name and a description.

## `resources/views/codex/partials/attribute-values.blade.php`

A definition list: attribute name, then every period in timeline order, the baseline first.

There is no "current value". The codex is not anchored to a moment — an attribute is a
sequence of values along the timeline, and only a scene gives one of them primacy. That is the
as-of panel's job, not this page's.

Reuses nothing from `attribute-timeline.blade.php` — that partial carries the period editors.

The as-of panel (`codex/partials/as-of.blade.php`) already renders a compact read-only
attribute list. Follow its `<dl>` shape so the two read surfaces look alike.

## Reuse

`x-card`, `x-badge`, `x-collapsible-card`, `x-icon-edit-link`, `x-icon-delete-button`,
`x-icon-dialog-button`. Referencing scenes use the `x-table` family, matching the codex list.

No Alpine on this page beyond the duplicate modal and any collapsible card.

## Scale

The referencing-scene list is the one section that grows without bound — a main character in a
400-chapter serial appears in most of them. Show the first 20 in timeline order with a "show
all" toggle that reveals the rest in place. No new route: the scene index is book-scoped and
has no codex filter, so there is nothing to link to.
