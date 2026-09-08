# UI

## The ledger page

One page per book: `books/{book}/reveals`. Two lists — **Known by the end of this book** and
**Still hidden** — in the style of the shipped codex read views (`codex-entry-read-view`).

- A book selector at the top, so "up to book 4" is one control, not four pages.
- Each row: the fact, its type, and where it was told (book, chapter, scene) or "never".
- Rows link to the fact, not to the reveal. The reveal is an annotation, not a destination.
- Group by fact type — entries, attribute values, events — because the three read very
  differently and a mixed list is soup.
- Paginate with `x-pagination-bar`, like every other long list.
- **Counts in each heading.** "Known (48)" / "Still hidden (12)" is the number she opens the
  page for.

Empty states are three, not two: no facts at all, no facts marked yet, and a filter with no
matches. The middle one is the interesting one — it should say what marking does and how to
start, since a fresh project's ledger is empty by definition.

## Marking a fact

Not a page. An inline control wherever the fact already lives:

| Fact | Where |
| --- | --- |
| Codex entry | the entry's read and edit views |
| Attribute value | the attribute timeline row |
| Event | the event's row and read view |

Three states, one control:

```
Reader learns this:  [ Not marked ▾ ]     ← unmarked
                     [ Chapter 12 — The well ▾ ]  [ ✎ ]
                     [ Never told ▾ ]
```

A `x-select` of the project's scenes is wrong at serial scale — that is 1600 options. **Use
the same shape the scene list just shipped for jumping:** a scene picker narrowed by book and
chapter, not one flat list. If that picker does not exist as a component yet, this feature
builds it, and it should be reusable rather than inlined here.

## The leak warning

An inline `x-alert variant="warning"` on the fact and a marker on its ledger row. Wording is
the design, because the check is approximate:

> Chapter 4 mentions Marnborne before this reveal in Chapter 11. Worth a look — a mention is
> not always a disclosure.

Never "error", never a count of problems in a heading. It is a prompt, not a verdict.

## Attribute values — two time axes

The row already reads "hair colour, from *The Second Curse*". A reveal adds "told in
*Chapter 12*". Two dates on one row, meaning different things, is the clearest way for this
feature to confuse someone.

**Label both, always, even when only one is set.** "True from …" and "Told in …". An unlabelled
date on a row that already has one is the failure mode.

## Files

| File | Change |
| --- | --- |
| `resources/views/reveals/show.blade.php` | new — the ledger page |
| `resources/views/components/reveal-control.blade.php` | new — the three-state control |
| `resources/views/components/scene-picker.blade.php` | new — book/chapter-narrowed scene select |
| codex entry, attribute timeline and event views | the control, inline |
| `documentation/features/` | a new guide; link it from the category index |

No Alpine beyond what the picker needs. No new colour tokens — `x-alert` and the codex read
views already carry the vocabulary.
