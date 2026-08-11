# Overview

Per-user typeface and text sizing, built as the second axis of the shipped theme feature:
same `<style>` block, same config-not-database shape, same "authenticated preference,
guests get the config default" rule.

## What ships

| Preference | Stored on | Values |
|---|---|---|
| UI font | `users.ui_font` | slug from `config/fonts.php`, `null` = default |
| Manuscript font | `users.manuscript_font` | same list, `null` = default |
| Text scale | `users.text_scale` | discrete step slug (`normal`, `large`, `larger`) |
| Manuscript line height | `users.manuscript_leading` | discrete step slug (`tight`, `normal`, `loose`) |

Every value is a slug from a fixed list. No free-form number, no unit, ever reaches CSS —
that is what keeps the `{!! !!}` style block safe without a second validation pattern.

## Goals

- Five bundled families, plus four no-download ones, choosable independently for chrome
  and manuscript.
- Text scale affects the whole app (rem-based), not the manuscript alone.
- Existing users keep the look they have today (see *Default change* below).
- Guests and unauthenticated surfaces render the config defaults.

## Non-goals

- Colors (`theme-switcher`, shipped), measure/line length (future width container), text
  alignment, uploads, per-project typography.
- A `fonts` table. Nothing about a family varies per request except which slug is active.

## Default change

`--font-sans` is Atkinson Hyperlegible Next today (`resources/css/app.css`), chosen for the
author's astigmatism; it is the whole app's font for everyone. The default becomes Inter.

**Existing users are not migrated.** The migration writes no value into the new columns —
`null` means "follow `config('fonts.default_ui')`", the same rule `theme_slug` follows, so
the app-wide default stays changeable in one file. This *does* change the look for anyone
who never picks a font, including the author. Accepted: the picker is one setting away and
the release note says so.

## Acceptance criteria

- A user picks a UI font and a manuscript font; both persist and repaint every page.
- Text scale and line height persist and apply.
- A logged-out visitor on `/`, the login page or a shared scene gets the config defaults,
  whatever any user stored.
- A stored slug that no longer exists in config falls back to the default instead of
  throwing (a family can be removed after users picked it).
- A tampered POST value is rejected by the Form Request and never reaches the style block.
- The picker is keyboard-operable: native radios, arrow keys, no Alpine required.
