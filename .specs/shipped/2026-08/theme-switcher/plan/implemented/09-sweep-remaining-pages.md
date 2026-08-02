# 09 — Sweep: remaining pages

~250 usages across 13 small folders. High volume, low judgement — by this point every decision
has been made in tasks 05–08.

## Scope

`resources/views/` — `revisions` (45), `scenes` (37), `projects` (27), `dashboard.blade.php`
(26), `story` (24), `chapters` (24), `events` (18), `auth` (17), `acts` (12), `profile` (11),
`search` (9), `shared` (7), `plotlines` (7), plus any stray view not covered by 05–08.

Does **not** include `welcome.blade.php` — task 13 rewrites it wholesale.

## Depends on

08.

## Key decisions already made

- Nothing new is decided here. If a usage does not fit an existing token, **stop and raise it**
  rather than inventing a token in a bulk task — a vocabulary gap found this late is a signal,
  and it belongs in `resolution-log.md`.
- `views/auth` is the login/register screens under `layouts/guest`. Rename its classes to tokens;
  do not redesign — a custom look for login is separate, later work.
- `views/shared` renders for an unauthenticated stranger holding a share link, so it paints with
  `config('themes.default')`, not the author's preference. Nothing to do beyond the rename, but
  check it in a browser while logged out.

## Consult

`expanded/architecture.md` → *Migration map*.

## Tests

- `NoHueNamedColorsTest` allow-list now covers only `resources/css`, `resources/js`, `app/`, and
  `welcome.blade.php`.
- The bulk of the feature suite touches these pages — `SceneTest`, `ActTest`, `ChapterTest`,
  `StoryTest`, `ProjectTest`, `SearchTest`, `RevisionBrowserTest`, `SceneShareTest`,
  `ProfileTest`, `EmptyStateTest`, `PlotlineTest`, `EventTest` — all stay green.
- Log out and load a share link under the default preset.
