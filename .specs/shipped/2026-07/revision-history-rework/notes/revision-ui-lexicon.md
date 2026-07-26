---
title: Revision & Diff UI — Lexicon
context: a-vibe-coded-story-organizer / revision system
status: reference (non-authoritative, not a spec)
date: 2026-07-24
---

# Revision & Diff UI — Lexicon

Terms that came up while designing the revision history and compare screens.
Grouped by area. Sources at the bottom.

---

## 1. Revisions & diffs

| Term | Meaning | Why it matters here |
|---|---|---|
| **Revision** | An immutable snapshot of a field's content at a point in time. Once written, never edited. | Your revert creates a *new* revision instead of rewinding — that's what makes the history append-only and trustworthy. |
| **Append-only history** | New facts are added, nothing is rewritten or deleted. Git, event sourcing and MediaWiki all work this way. | Guarantees the history view never lies. The price is storage growth. |
| **Head / current revision** | The revision that equals the live content right now. | Needs to be visibly marked. "Revert to this" is meaningless on head. |
| **Diff** | The computed difference between two versions of a text. | The whole compare screen is one diff rendering. |
| **Base / target** (git calls it `base...head`) | The two ends of a diff. Base = the older, "before" side; target = the newer, "after" side. | Your left combobox is base, right combobox is target. Fixing which is which is exactly what stops backwards diffs. |
| **Diff direction** | Whether the diff is read old→new or new→old. Same two revisions, opposite sign: additions become deletions. | MediaWiki never lets the user choose. Neither should you. |
| **Line-level vs word-level diff** | Line-level marks whole changed lines; word-level (sometimes "intra-line") highlights the changed words inside a line. | Your screenshot shows word-level (`ccrorazon test` highlighted inline). Better for prose; more expensive to compute. |
| **Unified vs side-by-side** | Unified = one column with `+`/`-` markers (what `git diff` prints). Side-by-side = two columns. | You chose side-by-side, which is right for a writer comparing prose. |
| **Myers algorithm** | The standard diff algorithm (1986) behind git and most diff libraries — finds the shortest edit script between two sequences. | You almost never implement this; you pick a library that does. Useful to know the name when comparing PHP diff packages. |
| **Hunk** | One contiguous changed region of a diff, usually shown with a few unchanged lines of context around it. | Your list row shows the first hunk. A find-and-replace produces forty of them — hence the truncation rule. |
| **Source diff** | Diffing the stored markup (wikitext, Markdown, HTML) rather than what the reader sees. | Right for `Scene.contents`, because Markdown is what she actually types. |
| **Visual diff** | Rendering both versions, then comparing the *output* — so formatting changes read as "bolded", not as inserted tags. | Right for Tiptap fields, because she never sees the HTML. Also catches moved paragraphs, which a source diff reports as a delete plus an unrelated insert. |
| **`wikidiff2`** | MediaWiki's diff engine: line-level first, then word-level inside changed lines. | Its `maxWordLevelDiffComplexity` cap — degrade to line-level rather than grind on a huge change — is the pattern to copy. |
| **Save batch / correlation id** | An identifier generated once per write request and stamped on every row that request creates. | Your `save_id`. It's what makes "undo that whole save" possible across three per-field revision streams. |
| **`<s>` vs `<del>`** | `<s>` marks text no longer accurate; `<del>` marks text removed from the document. Different semantics, not synonyms. | Keep strikethrough on `<s>` so `<del>` stays free to mean "the diff removed this". |

---

## 2. Computation & caching

| Term | Meaning | Why it matters here |
|---|---|---|
| **Compute-at-write vs compute-at-read** | Do the work once when the data is created, or every time someone looks at it. | A diff between two immutable revisions is a *constant*. Computing it at write time and storing it is the correct call. |
| **Precomputed / denormalised column** | A stored value derived from other data, kept for read speed. Duplicates information on purpose. | Your per-row "what changed" summary is this. The tradeoff: if you later change the diff algorithm, stored summaries go stale and need a backfill migration. |
| **Lazy computation / memoization** | Compute on first request, then cache the result. | Tempting middle ground, but the *first* visitor to a 90-revision history still pays for 20 diffs. Write-time wins here. |
| **Backfill migration** | A one-off job that populates a new column for existing rows. | What you'd need if you ever change how the summary is worded or computed. |
| **N+1 problem** | Rendering a list of N rows and firing one extra query (or one extra computation) per row. | The failure mode "compute 20 diffs on page render" is the same shape. |
| **Cursor / boundary row** | Because each row diffs against its predecessor, the first row of page 2 needs a row from page 1. | Fetch N+1 rows per page, render N. Small detail, easy to forget. |

---

## 3. UI patterns

| Term | Meaning | Why it matters here |
|---|---|---|
| **Combobox** | A text input **plus** a popup list of options. Not an HTML `<select>` — that's a "listbox" / "select-only" control. | The moment you put a filter field inside your dropdown, you've built a combobox and inherit its expectations. |
| **Select-only combobox** | A combobox that looks and behaves like a native select but is custom-built (no free typing). | The W3C APG has a worked example for exactly this — worth copying rather than inventing. |
| **Typeahead / autocomplete** | Typing narrows the option list. APG distinguishes *list autocomplete* (list filters, input unchanged) from *inline autocomplete* (input auto-completes as you type). | Your "type in the select to search history" is list autocomplete with manual selection. |
| **Constrained input / invariant enforcement** | Making an invalid state unreachable in the UI instead of validating it afterwards. | Disabling every option older than the left selection in the right combobox. Same trick as a date-range picker where end can't precede start. |
| **Prior art** | An existing, battle-tested solution to the same problem. | MediaWiki history + diff is the prior art for this feature. Copying it is not laziness; it's a decade of usability testing you get for free. |
| **Affordance** | A visual cue telling the user what an element does. | A "revert" button that silently disappears on head is a weak affordance — a "current" badge is a strong one. |
| **Progressive disclosure** | Show the common case; hide advanced controls until asked for. | The filters living inside the dropdown panel rather than on the page is this. |

---

## 4. Accessibility (ARIA)

| Term | Meaning | Why it matters here |
|---|---|---|
| **ARIA** | *Accessible Rich Internet Applications* — a W3C spec of HTML attributes (`role`, `aria-*`) that tell assistive tech what a custom widget *is*, when the raw HTML doesn't say so. | A `<div>` styled to look like a dropdown is, to a screen reader, just a div. ARIA is how you say "this is a combobox". |
| **APG (ARIA Authoring Practices Guide)** | W3C's companion guide: for each widget pattern, the required roles, states, and keyboard behaviour, with working examples. | The thing to build your Alpine dropdown against. |
| **Role** | `role="combobox"`, `role="listbox"`, `role="option"` — declares what an element is. | Three roles is most of what your dropdown needs. |
| **State / property** | `aria-expanded` (is the popup open), `aria-selected`, `aria-controls`, `aria-activedescendant`. | These are what change as the user interacts. Forgetting to update them is the usual bug. |
| **`aria-activedescendant`** | Lets DOM focus stay on the text input while a *different* element (the highlighted option) is announced as focused. | This is how arrow-key navigation in a combobox works without moving real focus out of the input. |
| **Focus management** | Deciding where keyboard focus goes when a widget opens, closes, or is dismissed. | Escape should close the popup and return focus to the input. Native `<select>` gives you this free; a custom one does not. |
| **Expected keyboard contract** | For a combobox: ↓/↑ move through options, Enter selects, Escape closes, Home/End jump, typing filters. | Users who never touch a mouse will try all of these. |
| **Colour is not information** | Any meaning carried by colour must also be carried by shape, symbol, or text. | Insertions and deletions need a `+` / `−` gutter mark, not just a green/red tint. |
| **Visually-hidden text** | Text present for screen readers but clipped from view (a `.sr-only` class). | How you announce "inserted" / "removed", since `<ins>`/`<del>` announcement is inconsistent across screen readers. |
| **EAA (European Accessibility Act)** | EU legislation, in force since June 2025, imposing accessibility requirements on certain digital products and services. | Already on your radar from the EPUB export spec — the same reasoning applies to the app's own UI. |

---

## 5. State & URLs

| Term | Meaning | Why it matters here |
|---|---|---|
| **State in the URL** | Encoding what the page is showing as query parameters (`?from=12&to=40`). | Makes a comparison bookmarkable, shareable, and survivable across a back-button press. Also removes the need for server-side session state. |
| **Idempotent GET** | A GET request should only read, never change anything, and can be repeated safely. | The compare page is a pure GET. Revert must be POST/PATCH — never a link. |
| **Deep link** | A URL that lands directly on a specific view rather than an entry page. | MediaWiki's `Special:Diff` exists purely to make diffs deep-linkable. |
| **Stale view** | The page still shows data that changed underneath it. | After a revert, the compare screen you're looking at is no longer the whole story. Redirect back to the history rather than leave it sitting there. |

---

## Sources

- [Combobox Pattern — W3C ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/) — roles, states, and the full keyboard contract.
- [Select-Only Combobox Example — W3C APG](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/examples/combobox-select-only/) — the closest worked example to your dropdown.
- [Help:Diff — MediaWiki](https://www.mediawiki.org/wiki/Help:Diff) — diff rendering, and the "left radio = older, right radio = newer" selection rule.
- [Help:History — MediaWiki](https://www.mediawiki.org/wiki/Help:History) — the history list as the entry point to diffs.
- [API:Compare — MediaWiki](https://www.mediawiki.org/wiki/API:Compare) — how MediaWiki models a diff request as a pair of revision ids.
- [Wikidiff2 — MediaWiki](https://www.mediawiki.org/wiki/Wikidiff2) — the source-diff engine, and the word-level complexity cap worth copying.
- [Visual diffs — MediaWiki](https://www.mediawiki.org/wiki/Visual_diffs) — comparing rendered documents instead of markup.
- [VisualEditor/Diffs — MediaWiki](https://www.mediawiki.org/wiki/VisualEditor/Diffs) — visual diff behaviour, default since MediaWiki 1.41.
