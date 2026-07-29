# Word Count — architecture

## `App\Support\WordCounter`

One class, one job: text + kind → integer. Constant/reference logic, so `app/Support`
alongside `RichText` (`CLAUDE.md` → *Where logic lives*).

```php
public static function count(?string $value, FieldKind $kind): int
```

Four steps, in order:

1. **Strip fenced code blocks** (` ``` ` and `~~~`) from Markdown input, before rendering.
   Inline code survives — see `open-questions.md` Q2 for why the two differ on purpose.
2. **Render to text by kind:**
   * `FieldKind::Rich` → `RichText::toPlainText()`.
   * `FieldKind::Markdown` → `Str::markdown()` then `RichText::toPlainText()`. Rendering
     first is what stops `**bold**` counting as one word and `# ` as another.
   * `FieldKind::Plain` → as-is.
3. **Split on whitespace:** `preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)`.
   Unicode-aware — an accented French word is one word.
4. **Drop non-words:** any token matching neither `\p{L}` nor `\p{N}`. Removes `* * *`
   dividers, standalone em-dashes and stray punctuation; keeps `1,234` and `3.5`.

> [!IMPORTANT]
> Step 2 depends on `RichText::toPlainText()` breaking on **every** block boundary, not just
> `</p>`/`<br>`. It does not today — `open-questions.md` Q9 — so task 1 fixes it before
> anything counts. Without that fix a heading glues into the next paragraph and every
> chapter undercounts.

**Why one entry point and not a method per kind:** the caller has a `FieldKind` from
`AutosavableFields::kindOf()` already. Anything else makes every caller re-derive it.

## The write path — a model hook, not a controller

`$model->save()` is the single choke point every write goes through:

| Path | Where |
|---|---|
| Autosave PATCH | `FieldAutosaveController::update()` — `$model->save()` |
| Manual save | `SceneController::update()` |
| Revert / undo a save | `RevisionReverter::restore()` — assigns then `save()` |
| Import | `ProjectGraphImporter` |
| Seeders | `MelusineSeeder*` |

So in `Scene::booted()`:

```php
static::saving(function (Scene $scene): void {
    if ($scene->isDirty('contents')) {
        $scene->word_count = WordCounter::count($scene->contents, FieldKind::Markdown);
    }
});
```

* **`saving`, not `saved`** — it sets an attribute on the row being written, so it costs no
  second `UPDATE` and cannot half-apply.
* **`isDirty('contents')`** — renaming a scene must not re-count its prose.
* This is the intended exception in `CLAUDE.md`: *invariants and lifecycle* belong in the
  model. It is the same shape as the existing `position` auto-assignment.

> [!IMPORTANT]
> Putting this in `FieldAutosaveController` instead would leave the count stale after a
> **revert** — `RevisionReverter` never touches that controller. A count that is right until
> someone uses Undo is the worst version of this feature.

## Reading totals

Two shapes, both in the controller per `CLAUDE.md` (no query scopes for index filtering):

**1. Scenes already loaded** — the story overview eager-loads `chapters.scenes`
(`StoryController:15`). Sum in PHP; **zero** extra queries:

```php
$chapter->scenes->sum('word_count')
```

**2. Scenes not loaded** — `withSum`, one grouped query for the whole page:

```php
$chapters = $act->chapters()->withSum('scenes as word_count', 'word_count')->get();
$acts = $project->acts()->withSum('chapters.scenes as word_count', 'word_count')->get();
```

> [!WARNING]
> Never `$chapter->scenes->sum(...)` in a Blade loop over unloaded scenes — that is the N+1
> this design exists to avoid. Eager-load or `withSum` in the controller; the view only
> renders.

A `Chapter::wordCount()` / `Act::wordCount()` accessor is **not** proposed: it would hide
whether a query fires, which is the one thing the caller must know. See `open-questions.md`
Q4.

## The live counter (JS)

`resources/js/word-count.js` + co-located `word-count.test.js` (vitest, the convention from
`resources/js/autosave/*.test.js`).

* Exports `countWords(text)` — whitespace split only.
* An Alpine component reads the editor's text and recounts, **debounced ~150 ms**, on input.
* Reads `editor.getText()` for the TipTap-backed `x-wysiwyg` (both rich and markdown mode)
  and `.value` for a plain textarea. `getText()` already returns *rendered* text even in
  markdown mode, because the editor holds a ProseMirror document and only serializes
  markdown on save (`open-questions.md` Q7).

> [!IMPORTANT]
> **The JS count is indicative, and deliberately not exact.** It does not strip fenced code
> blocks and does not apply the non-word rule — doing so would mean a second Markdown parser
> in the browser to serve a number that is about to be replaced anyway. The **server is
> authoritative**.
>
> The two therefore disagree while typing — a scene with a fenced block reads high. That is
> reconciled, not tolerated: `FieldAutosaveController`'s JSON returns `word_count`, and the
> live counter **snaps to it on every save** (Q3). This is what stops "indicative" turning
> into "wrong".

The stored count is **never** written from the browser.

## Authorization

Nothing new. Counts are read through already-authorized project/scene routes; no endpoint is
added. If Q3 lands on returning the count in the autosave response, that endpoint's existing
`ProjectPolicy` check already covers it.
