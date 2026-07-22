---
title: Expand Tip Tap — Open Questions
---

# Open questions

Each is phrased as a sharp yes/no or pick-one, with a recommended answer, so
`plan-tasks`' grilling pass has a concrete agenda rather than open-ended ambiguity.

1. **Do callouts and the widened tag set also apply to the 8 HTML `RichTextFields`
   fields, or only to `Scene.contents` (Markdown mode)?**
   `architecture.md`'s callout section and `spec.md` both note callouts need "a
   `data-callout-type` attribute... only if callouts should also work in the 8 HTML
   `RichTextFields`, not just `Scene.contents`" — this was never actually decided, only
   flagged. Tables/images/task-lists are unambiguous (both formats, per `spec.md`'s
   "decided — support them" language) but callouts specifically hedge.
   **Recommendation: yes, same tags/attribute in both formats.** The whole point of the
   allow-list being one shared source of truth (`RichTextFields::ALLOWED_TAGS`) is that
   it doesn't fork per field; a `Project.description` written as "important context"
   loses nothing by also being able to use a callout box, and maintaining two
   allow-lists (one per format) is the kind of duplication CLAUDE.md's DRY guidance
   warns against.

2. **How does `RichTextFields::purifierAllowedHtml()` grow to carry `img`'s
   `src`/`alt`/`title` (and optionally `width`/`height`) and the task-list checkbox's
   state attribute, given `ALLOWED_TAGS` today only special-cases one tag (`a[href]`)?**
   `architecture.md` flags this as a real mechanism gap, not just a data addition — the
   current `array_map` in `purifierAllowedHtml()` treats every tag as bare except `a`.
   **Recommendation:** replace the single `$tag === 'a' ? 'a[href]' : $tag` ternary with
   a small `ALLOWED_ATTRIBUTES` map (`class-string tag => list<attribute>`), keyed the
   same way `FIELDS` is keyed by model — one added constant, not a new class, keeping
   `RichTextFields` the single source of truth per its own docblock.

3. **Does image resize (`width`/`height`) ship at all in v1, and if so, in which
   format(s)?**
   Resize is lossless in HTML mode (the attribute round-trips fine — HTML stays HTML)
   and lossy in Markdown mode (the whole reason it's on the fallback-warning list).
   Non-goals only excludes *upload*, not resizing an existing reference.
   **Recommendation: ship resize for HTML-mode fields only** (`Image.configure({ resize:
   true })` when `! isMarkdown`, `false` when `isMarkdown` — already `architecture.md`'s
   plan for the markdown-mode half; this question is just confirming the HTML-mode half
   is a "yes, do it" rather than "leave both off for v1 simplicity"). Shipping it
   HTML-only is a natural product win (the fallback-list entry becomes purely
   "unexpected/pasted" content, never UI-authored) with no round-trip risk.

4. **Does table cell merge/split ship at all in v1, and if so, in which format(s)?**
   Same shape as question 3: lossless in HTML mode, lossy in Markdown mode.
   **Recommendation: ship merge/split for HTML-mode fields**, hidden entirely in
   Markdown-mode fields (`architecture.md`'s existing plan) — for the same reason as
   image resize, this closes the loop so both "prevent, don't warn" bullets in
   `spec.md`'s fallback-policy section are fully realized rather than half-shipped.

5. **Paste-time / import-time transformation of unmatched HTML wrapper tags — pick this
   up now, or leave fully out of scope?**
   `spec.md` explicitly declines to decide this ("real additional scope, not a toggle,
   and not decided here; left for whoever picks up this spec's tasks to size
   separately"). Restating it here so `plan-tasks` makes an explicit call rather than it
   falling through silently.
   **Recommendation: leave out of scope for this spec's plan.** The warn-from-a-list
   fallback (`autosave-with-revisions`' concern) already covers the residual case
   adequately for v1; a paste-handler transform is a meaningfully larger, separable
   piece of work that deserves its own sizing pass rather than riding along here.

6. **Vitest wiring: co-located `*.test.js` files next to source, or a separate
   `resources/js/__tests__/` directory?**
   No existing JS test file to match a convention against — this project has no JS test
   tooling today (`testing.md`). Purely a repo-convention choice, not a design decision,
   but worth pinning before the first test file is written so it isn't bikeshedded mid-PR.
   **Recommendation: co-located `resources/js/wysiwyg.test.js`**, mirroring the PHP
   convention of tests living near what they cover in spirit (even though PHP tests live
   in a parallel `tests/` tree for framework reasons Vite doesn't share) — Vite/vitest's
   own ecosystem convention favors co-location, and it keeps the new JS test surface
   visible next to the file it's pinning rather than growing a same-shaped shadow tree.
