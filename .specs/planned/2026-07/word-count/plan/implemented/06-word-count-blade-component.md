# Task 6 — `x-word-count` display component

## Scope

`resources/views/components/word-count.blade.php`:

```blade
<x-word-count :count="$chapter->word_count" />
<x-word-count :count="$scene->word_count" variant="inline" />
```

* Props: `count` (int, required), `variant` (`muted` default | `inline`).
* Renders a pluralised, thousands-separated string via `trans_choice` —
  `0 words` / `1 word` / `1,234 words`.
* `muted`: `text-xs text-gray-400`. `inline`: inherits size, for table cells.
* Zero renders as `0 words`, never blank and never a dash. Zero is a real answer; blank
  reads as "unknown".

One place formats a count, so a list and a header can never disagree about `1,234` vs `1234`.

## Depends on

Task 4 (there is a count to render). Independent of 5 and 7.

## Key decisions already made

* Pluralisation through `trans_choice`, not a ternary — the app is translated (`language`
  on projects, French/Italian seeders exist).
* No icon. A number with its unit is self-explanatory; an icon-only count fails at zoom and
  for screen readers.
* The component takes an **int**, never a model — so it is equally usable for a scene's own
  count and for a computed `SUM`.

## Consult

`../expanded/ui.md`.

## Tests

`tests/Feature/WordCountComponentTest.php`, following the existing
`IconButtonComponentTest` / `BladeComponentCompilationTest` style:

* `0` → `0 words`; `1` → `1 word`; `1234` → `1,234 words`.
* Both variants render and carry their classes.
* The component compiles (it should be picked up by `BladeComponentCompilationTest`
  automatically — confirm rather than assume).
