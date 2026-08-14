# 04 — Chapters and scenes

**Depends on:** 03.

## Scope

* Walk `outline/` and fill the act: chapter directories → `Chapter`, their `*.md` → `Scene`.
* Naming, ordering and fallbacks per `../expanded/architecture.md` → *Reading the tree* steps 4–5.
* The Markdown content path: LF-normalized body → `contents`, and the strip-and-mark handling below.
* Counting the skips (loose files under `outline/`, non-`.md` files in a chapter directory) and the
  empties into the report task 03 already prints.
* Characters are task 05.

## Key decisions

* Sort numerically on the `NN-` prefix, then assign `position` as the 1-based index — source
  prefixes have gaps and duplicates.
* Scene name from `title:`, chapter name from `folder.txt`'s `title:`; both fall back to the
  filename with the prefix stripped and `_`/`-` restored to spaces.
* Only `title:` is read from a scene header. `summaryFull`, `POV`, `notes`, `compile`, `charCount`
  are read past — `description` and `notes` on the imported scene stay null.
* **Disallowed HTML:** run the body through `ContentSanitizer::assertMarkdownAllowed()`. On
  violation, replace the offending raw-HTML passthrough in the *Markdown source* with
  `[INVALID CONTENT REMOVED]` on its own line, re-check, and count it. Never abort, never leave the
  scene out. Only raw HTML in the source can trip the check — CommonMark's own output is inside the
  allow-list.
* Empty chapters and empty-bodied scenes import normally and are counted.
* Save scenes one at a time through Eloquent — `Scene::booted()` owns `word_count` and the snapshot.

## Tests

Extend `tests/Feature/ManuskriptImportCommandTest.php`: chapter and scene counts and their exact
ordered names; `position` contiguous 1..n despite `00-`/`10-` prefixes; `contents` is the body
verbatim, LF-normalized, with no header block; the missing-`title:` fallback; the disallowed-HTML
scene imports with the marker line and is counted; the empty chapter and empty scene exist and are
counted; the loose `outline/` file is named in the output.
