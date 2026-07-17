---
status: shipped
shipped: 2026-07-10
---

# Extract misplaced and duplicated helpers

An audit prompted by `StaticSiteExporter::plainText()` — an HTML-to-plain-text
converter living inside the export service, where it has nothing to do with building a
zip. It is a rich-text concern that has drifted into the wrong module. The audit found
one clear duplication alongside it and one presentation helper duplicated across views.

Extract each to the home the architecture already implies, following the CLAUDE.md rule
that **application workflow lives in a Service, Support, or trait — never in a
controller, model, or template beyond "resolve → authorize → delegate → respond."**

## 1. `plainText()` — wrong home (do first; it seeded this audit)

`StaticSiteExporter::plainText()` (`app/Services/StaticSiteExporter.php:147`) strips a
stored rich-HTML fragment to prose: it knows the HTML shape the editor produces (`</p>`,
`<br>` → newlines, decode entities, collapse blank runs). That knowledge belongs to the
rich-text feature, whose single source of truth is already `App\Support\RichTextFields`
+ `App\Services\HtmlSanitizer` + the `SanitizesRichHtml` trait — not to a zip builder.

* Move the logic to the rich-text module (e.g. a `plainText()` / `toPlainText()` on a
  small `App\Support\RichText` helper, or a static method beside the sanitizer). The
  exporter's README builder calls it instead of owning it.
* Note the honest caveat: today there is only **one** caller, so this is relocation for
  cohesion, not reuse — it does not violate "no abstraction before a second caller"
  because no new abstraction is created, only a move to the correct existing module. A
  future import/preview that needs prose from stored HTML then reuses it for free.

## 2. `swapPosition()` — triplicated across controllers (strongest win)

The position-swap logic CLAUDE.md explicitly names as an extraction candidate is copied
verbatim in three controllers, differing only in the model class and the sibling-scope
column:

* `ActController::swapPosition()` (`app/Http/Controllers/ActController.php:92`) — scoped by `project_id`
* `ChapterController::swapPosition()` (`app/Http/Controllers/ChapterController.php:106`) — scoped by `act_id`
* `SceneController::swapPosition()` (`app/Http/Controllers/SceneController.php:149`) — scoped by `chapter_id`

Extract once — a real second (and third) caller exists, so this clears the CLAUDE.md
bar. Prefer a shared trait (`HasPositionAmongSiblings` / `Ordered`) on the models, or a
`PositionSwapper` service, that swaps a model with its adjacent sibling within an
owner-scoped set. The scope key is the one thing that varies, so it must be a parameter
or a per-model hook, not hard-coded. Wrap the two-row swap in a transaction (multi-step
write). `SceneController`'s `wantsJson()` branch stays in the controller — only the
swap moves.

## 3. Scene-contents Markdown render — duplicated in templates

`Str::markdown($scene->contents ?? '')` appears in three places, putting presentation
logic in Blade against the "keep presentation logic out of Blade" rule:

* `resources/views/story/index.blade.php:94`
* `resources/views/shared/scenes/show.blade.php:30`
* `resources/views/exports/book/chapter.blade.php:23` (via `$scenesContents`)

Give it one home — a `Scene` accessor (e.g. `renderedContents`) or a shared Blade
component — so the null-guard and the renderer choice live in a single place. Keep it
consistent with the `harden_deps` decision to depend on `league/commonmark` directly.

## Out of scope

* The exporter's internal `slug()` / `slugDir()` / `entityDir()` / `addFromString()`
  helpers — private, single-service, and genuinely export-specific; leave them.
* No behavior changes. Each extraction is a pure refactor; existing tests must stay
  green, and the moved position-swap and plain-text logic each gain a direct unit/feature
  test at its new home.
