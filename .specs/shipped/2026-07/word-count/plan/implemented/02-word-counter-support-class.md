# Task 2 — `App\Support\WordCounter`

## Scope

One class, one public method — the single definition of "a word" in this app:

```php
public static function count(?string $value, FieldKind $kind): int
```

Four steps, in order:

1. **Strip fenced code blocks** from Markdown input, before rendering — ` ``` ` and `~~~`
   fences, including info strings (` ```php `). Inline code is **not** stripped.
2. **Render to text by kind:**
   * `FieldKind::Rich` → `RichText::toPlainText()`
   * `FieldKind::Markdown` → `Str::markdown()` → `RichText::toPlainText()`
   * `FieldKind::Plain` → as-is
3. **Split**: `preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)`.
4. **Drop non-words**: discard tokens matching neither `\p{L}` nor `\p{N}`.

`null` / `''` → `0`.

Lives in `app/Support` beside `RichText` — reference logic, per `CLAUDE.md` → *Where logic
lives*. No model, no controller, no DB access.

## Depends on

Task 1 — step 2 is wrong without the block-boundary fix.

## Key decisions already made

* **Whitespace only splits words.** `one—two` = 1, `jack-o'-lantern` = 1, `1,234` = 1.
  Matches Word/Scrivener, which is what the writer will compare against.
* **A token needs a letter or a digit.** `* * *` = 0, a lone `—` = 0, `3.5` = 1.
* **Fenced blocks out, inline code in.** Deliberate asymmetry: a fence is marked as
  not-prose; inline code sits inside a sentence.
* **One entry point taking `FieldKind`**, not a method per kind — callers already hold a
  `FieldKind` from `AutosavableFields::kindOf()`.
* Strip fences with a regex on the **source**, before `Str::markdown()`. Do not render and
  then try to identify `<pre>` — cheaper and less fragile the first way.

## Consult

`../expanded/architecture.md` (the four steps), `../expanded/open-questions.md` Q1, Q2, Q2b.

## Tests

`tests/Unit/Support/WordCounterTest.php` — the full fixture table in
`../expanded/testing.md`, which is the specification of the rule. Include at minimum:

* empty / whitespace-only → 0
* `one—two` → 1; `jack-o'-lantern` → 1; `état d'âme` → 2
* `1,234` → 1; `3.5` → 1
* `* * *` → 0; lone `—` → 0; `" ... |` → 0
* `**bold** text` → 2; `# Heading` → 1; `[link](http://x.com)` → 1
* `` She typed `rm -rf` now `` → 5 (inline code counts)
* a fenced block alone → 0; `before` + fence + `after` → 2
* `<h1>Chapter One</h1><p>She waited.</p>` → 4 (proves task 1 landed)
