# UI

## The picker

`resources/views/admin/appearance/edit.blade.php` grows a second and third `x-card` below
the existing theme one, in the same form (one `Save`, one round-trip):

| Card | Control | Why that control |
|---|---|---|
| Interface font | radio list, one per family | native radios = arrow-key nav, no Alpine; matches the theme picker |
| Manuscript font | radio list, same families | separate choice is the point of the feature |
| Text size / Line spacing | radio list per axis, 3 steps each | a slider implies a continuum the config does not have |

- Each family's label carries its **reason** (`note` in config), not just its name — the
  list only helps if the reader knows why Lexend is there.
- Each family label is rendered **in that family** (`style="font-family: …"` from the
  config stack) so the list is its own preview. The stack is authored config, not input.
- The manuscript card additionally shows one sample paragraph of prose in the selected
  family, size and leading — a family name in 14px tells nobody how a page of it reads.
- `x-input-error` under each fieldset, as the theme fieldset does.

## Prose surfaces

The manuscript family and leading reach the page through two utilities on the existing
components — no new wrapper element:

- `resources/views/components/rich-text.blade.php` — add `font-manuscript` to the merged
  `prose` class list.
- `resources/views/components/wysiwyg.blade.php` — same on the editor surface, so what is
  typed matches what is read.
- `.prose` in `resources/css/app.css` gains `line-height: var(--manuscript-leading)`,
  beside the `--tw-prose-*` re-pointing already there. Same unlayered-after-the-plugin
  trick; the existing comment block explains why that wins.

Everything else in the app keeps `font-sans` and follows the UI choice automatically,
because `--font-sans` is what the style block overrides.

> [!WARNING]
> Tailwind Typography sets its own `line-height` per element size. The `.prose` override
> must come after `@plugin '@tailwindcss/typography'` — it already does — and applies to
> the block, so headings inside prose inherit it too. That is intended: a manuscript with
> a loose body and tight headings reads wrong.

## Keyboard & a11y

- Fieldset + `<legend>` per group; `sr-only` legend only where the card header already
  names it, as the theme fieldset does.
- The sample paragraph is decorative-adjacent but real text — do not `aria-hidden` it;
  a screen-reader user changing line spacing for a sighted partner is a real case.
- No live-preview JS. Apply on submit, matching the theme picker; see `open-questions.md`.
