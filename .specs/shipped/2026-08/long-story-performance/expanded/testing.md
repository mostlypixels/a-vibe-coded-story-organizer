# Testing

Extend `tests/Feature/StoryTest.php`; plain PHPUnit, `RefreshDatabase`,
factories, `actingAs`, `route()` helper.

## Overview render — `chapter` mode (default)

- Fresh project defaults to `chapter`; overview response contains only the first
  chapter's scenes, **not** a later chapter's scene body. Assert a later
  chapter's scene contents are absent.
- `?chapter={id}` renders that chapter; its scenes present, siblings' bodies
  absent.
- Chapter/act **numbering** correct for a mid-story chapter though only it is
  loaded (e.g. "Chapter 15") — guards the `forProject` vs `fromActs` swap.
- Act and book **word totals** in the header equal the whole-tree sums, not just
  the loaded chapter — guards the aggregate query.
- Pager: first chapter disables "previous", last disables "next"; middle links
  to correct neighbour ids.
- Empty project → "No acts yet." (unchanged path).

## Overview render — `book` mode

- With mode `book`, response contains every chapter's scene bodies (parity with
  today's behaviour).

## Mode update

- Owner PATCHes mode → persisted, redirect back.
- Invalid enum value → `assertSessionHasErrors`.
- Non-owner PATCH → 403.

## Authorization

- `?chapter={id}` where the chapter belongs to **another** project → 403 (not a
  200 leaking that chapter). The core cross-project guard.
- Non-owner GET of the overview → 403 (existing).

## Query budget

- Assert `chapter` mode does not load scene `contents` for non-current chapters
  — e.g. `DB::listen`/count, or assert peak query shape. Keep it simple: a
  no-N+1 assertion that the response is produced in a bounded query count
  regardless of chapter count.
