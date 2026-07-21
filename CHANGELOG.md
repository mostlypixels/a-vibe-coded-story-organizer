# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), adapted
so the heading answers *when something shipped*: each merged pull request (or
directly-landed feature) adds its own dated `## YYYY-MM-DD — <title> (#PR)` section at
the top, below `[Unreleased]`, grouping its entries by change type (`Added` / `Changed`
/ `Fixed` / `Removed`). `[Unreleased]` holds only work that has not merged to `master`
yet — when the PR carrying an entry merges, the entry ships under its dated heading.
The per-commit "why" lives in each commit message body; richer rationale for a change
set belongs in its pull request description.

## [Unreleased]

### Added

- **Configurable EPUB export.** The ebook export (**Admin → Export & import → Export →
  Ebook**) is now driven by a per-project **publication settings** form, so an author controls
  what the generated `.epub` contains instead of taking one fixed layout. Every option defaults
  to reproducing the previous export exactly, so an untouched project downloads the same book as
  before. The config page offers:
  - **Metadata toggles** — include (or omit) the author, publisher, rights, and ISBN in the
    book's Dublin Core metadata.
  - **Cover toggles** — include the project cover, and optionally a full-page **cover image per
    chapter** (uploaded on the chapter edit page).
  - **Content options** — show scene titles; show act / chapter / scene descriptions; and pick
    the chapter-heading format (e.g. `Chapter 1: Title`, just the title, or just the number) and
    the scene **divider** style (horizontal rule or a decorative flourish).
  - **Front & back matter** — four optional Markdown sections (dedication, acknowledgements,
    preface, postface) written on the project edit page, each independently toggled into the
    book. Their order relative to the table of contents and the story body is set with a sortable
    **section order** list (move up / move down), with the title page pinned first.
  - **Table-of-contents depth** — list acts only, acts with their chapters (the default), or a
    third level of per-scene links.
  - **Codex appendix** — an optional back-matter appendix built from the project's Codex:
    choose which entry types to include (characters / locations / organizations) and whether to
    embed each entry's first image, rendered as a heading page plus one page per entry.
- Projects now carry four optional front-/back-matter Markdown fields — dedication,
  acknowledgements, preface, postface — editable on the project edit page.
- **The archive round-trips all of it.** The publication settings travel in the `.zip` export
  (`data/publication-setting.json`), the four Markdown fields and any chapter cover images travel
  in `data/`, and all of it is restored on import. The export manifest `version` bumps to `2`
  once to cover every new field; version `1` archives still import cleanly, with the new fields
  left `null`/default. The publication settings are validated as **untrusted** input against the
  same rules as the config form: a malformed setting is logged, skipped, and the project imports
  its content on the default settings rather than failing the whole import (unknown appendix codex
  types are dropped individually). A project with no saved setting omits the descriptor and
  round-trips to the lazy default.

### Changed

- The Export & import "Export" and "Import" screens are now three server-rendered pages with a
  sub-navigation (Export / Ebook / Import) instead of a single Alpine-tabbed page; the sidebar
  entry and route names are unchanged.
- The EPUB export architecture and the new archive fields are documented in
  `documentation/architecture.md` (→ *EPUB export (publication settings)*) and
  `documentation/export-format.md`.

## 2026-07-21 — Upgrade the test toolchain to PHPUnit 13 and ParaTest 7.23 (#15)

### Changed

- Upgraded the development test toolchain to PHPUnit 13.2 and ParaTest 7.23 (from PHPUnit
  11.5 / ParaTest 7.8), pulling ~23 transitive `sebastian/*` and `phpunit/php-*` majors with
  them. ParaTest could not move past 7.8 while the PHPUnit constraint stayed on `^11.5`
  (ParaTest 7.23 requires `phpunit/phpunit ^13.2`), so the two only move together.
  `composer test` is unchanged and the suite passes as-is — no test, `phpunit.xml`, or
  assertion changes were needed, and PHPUnit 13 raised no deprecation or schema warnings.
  Both are dev-only dependencies, so the production image (`composer install --no-dev`) is
  unaffected.

> [!NOTE]
> After pulling this, Docker users need `make rebuild` rather than `make build` — `vendor/`
> lives in an anonymous volume that Compose carries over to a recreated container, so a plain
> build leaves the old PHPUnit 11 mounted on top of the new image.

## 2026-07-21 — EPUB export: XML declaration and config layout

### Fixed

- **The EPUB export could not render under `short_open_tag=On`.** The layout emitted its
  XML declaration as `{!! '<?xml …' !!}`, but Blade runs PHP's own lexer over the raw
  template before compiling `{!! !!}` into an echo — so `<?xml` was read as a short open
  tag during that pass and the build failed with `unexpected identifier "version"`,
  regardless of the `{!! !!}` wrapping. The declaration is now built by concatenating
  `'<'` with the rest, which never puts the two characters adjacent in the source.
  This surfaced in Docker: the official PHP images ship no `php.ini`, so PHP's compiled-in
  default (`short_open_tag=On`) applies there, while a typical host `php.ini` sets it Off.

### Changed

- The EPUB publication-settings form is grouped in a labelled box showing the loaded
  project's name, so it is clear which project the settings belong to, and the project
  picker's **Load** button is promoted to the primary style as the page's entry action.

### Added

- **Docker support.** Production (`Dockerfile`, `docker-compose.yml`) and development
  (`Dockerfile.dev`, `docker-compose.dev.yml`, `Makefile`) container setups, so the app
  can run without a local PHP/Node install. Documented in `documentation/docker.md`;
  `docker/entrypoint.sh` generates a fresh `APP_KEY` per instance on first boot rather
  than shipping a shared one.

### Changed

- **Composer dependencies refreshed** within the existing constraints (Laravel 13.20.0 →
  13.21.1, Guzzle 7.14.2 → 7.15.1, plus patch bumps). PHPUnit deliberately stays on
  `^11.5.50`; `brianium/paratest` is pinned behind it (7.23.0 requires PHPUnit `^13.2`),
  so those two must be upgraded together in their own change.

- **Docker: MailHog replaced with Mailpit.** MailHog has been unmaintained since 2020;
  Mailpit is a drop-in replacement on the same SMTP port, with the UI still at `:8025`.

### Fixed

- **Docker: Xdebug was installed but could never connect.** The image enabled the
  extension without configuring it, so it ran in the default `develop` mode — which does
  not include step debugging — and the compose file published port 9000 (Xdebug 2's
  default, and php-fpm's own port) even though Xdebug 3 dials *out* to the IDE. New
  `docker/xdebug.ini` sets `mode=debug` on port 9003 via `host.docker.internal`, with
  `start_with_request=trigger` so tests aren't slowed when not debugging.

- **Docker: mail was misconfigured in development.** `MAIL_MAILER=mailhog` is not a
  Laravel mail driver (`config/mail.php` defines no such transport), so sending mail from
  the dev container raised `Mailer [mailhog] is not defined`. Now `smtp` pointed at the
  mail catcher.

- **`composer test` failed inside the Docker container** with 248 failures that did not
  reproduce on the host. PHPUnit's `<env>` entries do not override variables already
  present in the real environment unless marked `force="true"`, so the container's
  `APP_ENV=local` beat `phpunit.xml`'s `APP_ENV=testing`. `ValidateCsrfToken` only skips
  itself in the `testing` environment, so CSRF was enforced and every write request in the
  suite returned 419. All `phpunit.xml` env entries are now forced, making `composer test`
  behave identically on the host, in the container, and in CI.

- **`make clean` could never run on Windows.** Make executes recipes through `cmd.exe`
  there, where the target's `rm -rf` does not exist, so the command aborted. It now
  selects `del` or `rm` from the `OS` variable. `documentation/docker.md` records the
  constraint, since it applies to any shell command added to a target.

- **Docker: `make rebuild` silently kept stale dependencies.** `vendor/` and
  `node_modules/` are anonymous volumes, and Compose carries those over when recreating a
  container — so rebuilding after a `composer.json`/`package.json` change left the old
  dependencies mounted over the new image. `make rebuild` now recreates with
  `--renew-anon-volumes`.

### Removed

- **Docker: the Redis container.** Cache, sessions, and the queue all use database drivers
  whose tables ship with Laravel's default migrations, so Redis was a second service doing
  nothing at this scale (`CACHE_STORE` is now `database`). `config/database.php` still
  reads the `REDIS_*` variables if a deployment later needs it.

- **Obsolete Compose `version:` key** from both compose files, and the end-of-life
  `docker-compose` (v1) invocation in the `Makefile`, now `docker compose`.

## 2026-07-17 — Accent-insensitive advanced search

### Added

- Project search now matches across accents: searching `Melusine` finds a `Mélusine`
  character (and the reverse), for every searchable field of every entity. A new
  `App\Support\AccentFolder` is the single source of truth — one 1:1 accent→base map, applied
  at the entity gate, the per-field label check, and the snippet highlighter so all three agree.

### Changed

- Search matching now runs in PHP (accent-folded `str_contains`) rather than a SQL `WHERE`
  clause, making it identical and portable across every supported database driver (a folding
  SQL expression is not — it overflows SQLite's parser on some builds). Snippet highlighting
  matches on accent-folded text but still renders the original accented characters inside
  `<mark>`, and matching is now uniformly case-insensitive across all drivers.

## 2026-07-17 — Strip HTML tags from rich-text field previews in search results

### Fixed

- Search result previews for rich-HTML fields (`Scene.notes` and any `description` field
  per `RichTextFields`) no longer show raw or HTML-escaped markup (e.g. `&lt;p&gt;`) — the
  preview now shows the reader's plain text, matching what other rich-text renders already do.

## 2026-07-17 — Extract workflow tooling from the skills (#7)

### Added

- `scripts/` toolbox extracted from the `.claude/` skills by the reworked
  `extract-tools-and-commands` skill: `spec-locate.sh`, `spec-advance.sh`,
  `plan-next-task.sh` (the `.specs` lifecycle mechanics), `serve-app.sh` /
  `stop-app.sh` (PID-file-based dev-server control with pre-flight checks), and
  `pr-land.sh` (push → PR → auto-merge → confirm-merged), each following a documented
  bash contract; indexed in `scripts/README.md`.
- `php artisan spec:draft` command scaffolding a stage-1 draft spec (prompts for
  missing input interactively, non-interactive for agents), with `config/specs.php`
  making the `.specs` base path injectable and a 6-test feature test.

### Changed

- `extract-tools-and-commands` skill elaborated from a four-line brief into a recurring
  extraction pass with selection criteria, an artisan-vs-bash decision rule, a bash
  script contract, and an audit → propose → approve → extract → rewire procedure.
- The mp-spec-expander, plan-tasks, ship-plan, ship-pr, draft-spec, and run-imagoldfish
  skills and the plan-implementer agent now delegate their mechanical command sequences
  to the extracted tools, keeping only the judgment and invariant rationale inline.

## 2026-07-17 — Month buckets for the .specs tree (#6)

### Changed

- **`.specs/` stages past draft now bucket features by month** —
  `.specs/<status>/<YYYY-MM>/<name>/` — so `shipped/` (21 features after two weeks, and
  the only folder that grows forever) stays listable. Drafts stay flat under
  `.specs/draft/<name>/` since a draft has no lifecycle date yet. Each pipeline stage now
  stamps its date in the spec frontmatter (`expanded:` / `planned:` / `shipped:`) and the
  bucket is that date's month; `SpecsStatusConsistencyTest` enforces the bucket shape,
  the date stamps, and bucket↔stamp agreement, alongside its existing status and
  name-uniqueness checks. The pipeline skills (`draft-spec`, `mp-spec-expander`,
  `plan-tasks`, `ship-plan`) and the `plan-implementer` agent locate features with the
  glob pair `.specs/draft/<name>/` + `.specs/*/*/<name>/`. All 21 shipped features moved
  to `shipped/2026-07/` (two missing `shipped:` stamps backfilled from git history), and
  the live docs that referenced old `.specs/shipped/<name>/` paths were updated.

## 2026-07-17 — PR shipping ritual & dated changelog (#5)

### Added

- **`ship-pr` skill** (`.claude/skills/ship-pr/SKILL.md`): the protected-`master`
  branch → commit → push → PR → squash-auto-merge ritual as one reusable skill;
  `ship-plan` step 9 now delegates to it instead of re-describing the dance.

### Changed

- **This changelog now uses dated sections.** Everything used to pile up under one giant
  `[Unreleased]` heading with no way to tell when an entry shipped. Each merged PR now adds
  its own `## YYYY-MM-DD — <title> (#PR)` section (convention documented in the header and
  `CLAUDE.md`); the existing entries were re-filed under dated headings where attributable
  (2026-07-14 → 2026-07-17) and an "Earlier" section for the rest.
- **Repository auto-merge enabled** (GitHub setting): `gh pr merge --squash --auto` now
  arms a PR to land itself when the `tests` check goes green — no manual watch-and-merge.

## 2026-07-17 — Workflow optimizations (#4)

### Changed

- **Workflow optimizations from the 2026-07-16 tooling audit.** `composer lint` is now the
  canonical Pint entry point (`composer lint -- --test` to check only) — CLAUDE.md,
  `documentation/code-style.md`, CI, and the `run-imagoldfish` skill all point at it. A
  committed project allowlist (`.claude/settings.json`) covers the project's own
  test/lint/artisan/build commands so implementer agent loops stop stalling on permission
  prompts. The `plan-implementer` agent runs on Sonnet by default (`ship-plan` escalates
  gnarly tasks to Opus per task) and no longer pre-reads every expanded spec doc — only
  the ones the selected task file links. The `run-imagoldfish` skill logs in with the
  seeded `admin@example.com` dev user instead of creating and deleting a throwaway user
  via tinker.

### Removed

- **`.claude/guidelines.md`** — it had drifted into a stale subset of `CLAUDE.md` (it still
  claimed there was no `app/Services` layer and no Scene/Act/Chapter feature tests).
  `CLAUDE.md` is the single maintained conventions file; the skills and agents that listed
  both now read only `CLAUDE.md`. Historical references in `.specs/shipped/` are left as-is.

## 2026-07-16 — CI merge gate & parallel test suite (#3)

### Added

- **CI merge gate.** GitHub Actions workflow (`.github/workflows/tests.yml`) runs the
  parallel test suite and `pint --test` on every push and pull request (PHP 8.5,
  ubuntu-latest, real frontend build so `@vite` views render). Branch protection on
  `master` now requires a pull request with a green `tests` check before merging —
  direct pushes are rejected. `pint.json` excludes the two hand-maintained Melusine
  seeder variants so the style check reflects the "never reformat those" convention.

### Changed

- **`composer test` now runs the suite in parallel** (`php artisan test --parallel` via
  `brianium/paratest`, new dev dependency): 4m18s → ~1m08s for 580 tests on the reference
  machine. Each worker gets its own in-memory SQLite database, so tests must not assume
  shared state across classes (they already don't, per `RefreshDatabase`).

## 2026-07-16 — Advanced search

### Added

- **Project-wide search page.** New `GET /projects/{project}/search` (last item in the primary
  nav) scans the string/text fields of Acts, Chapters, Scenes (contents + notes), Events,
  Plotlines, and Codex entries, with three match modes — all words (AND, the default), any word
  (OR), exact phrase — and renders results grouped like the menu: Timeline / Story / Codex
  sections, each stacking one full-width table per entity type, like the entity list pages
  (Codex splits per entry type; an initial 3-column grid was revised away — too narrow to
  read). Each matched *entity* is one table row: its name (linked to the edit page), the
  fields the terms matched in ("Name, Contents"), a ~120-character highlighted text preview
  of the first matching field (`<mark>`, escape-then-highlight so stored markup never renders
  live), and a trailing view button. Entity types with no matches are hidden, as are sections
  whose tables are all empty — only what matched renders. Plain GET form, no
  AJAX; an empty query is the normal landing state, not an error. Under the hood: the first
  `app/Services` service (`ProjectSearch`, a fixed six SELECTs per search with `LIKE`-wildcard
  escaping so literal `%`/`_` in a query match literally), a `SearchMode` enum, and a
  `SearchSnippet` helper — no new package, migration, or index. Result caps/pagination are
  deliberately deferred to the `search_pagination` draft spec.

## 2026-07-15 — Laravel 13 / PHP 8.5 upgrade

### Changed

- **Upgraded to Laravel 13.20.0 on PHP 8.5.7 (WAMP).** Bumped `composer.json` to
  `"php": "^8.5"` and `"laravel/framework": "^13.0"`, and switched WAMP's active Apache
  PHP module from 8.2.18 to 8.5.7. Maintenance upgrade with no behavior change: the only
  dependency the framework bump forced was `laravel/tinker` → `^3.0` (resolved to v3.0.2);
  no `config/*.php` or `bootstrap/app.php` change was required, and `composer test` stayed
  green (539 passed / 2013 assertions). `ext-imap` (removed from PHP core in 8.4) was
  intentionally not restored — the app does not use it.

## 2026-07-14 — Codex alias references (#2)

### Added

- **Manual codex reference resync.** New `codex:sync-references {project?}` artisan command
  rebuilds the `scene_codex_entry` pivot from scratch (every project, or one via the optional
  argument) — needed to backfill scenes that existed before `SceneReferenceMatcher` shipped, since
  normal saves keep the pivot in sync automatically and nothing else ever touches pre-existing data.
  The project edit page gains a matching **"Resync codex references"** button: its own footer form
  (`POST /projects/{project}/codex-references/sync`), separate from the main project-fields form so
  it submits independently, same `update` authorization as the rest of project editing.
- **Scene edit page shows which codex entries it references.** The edit form's sidebar gains a
  **"Codex references"** card listing every codex entry whose name or an alias whole-word-matches the
  scene's contents (as of the last save), a flat list ordered by `(type, name)`; each row links to
  the entry's edit page and shows its type label. A "Detected from the scene contents on last save."
  caption makes the no-AJAX, save-time refresh behaviour explicit. Read-only view of the derived
  `scene_codex_entry` cache; never rendered on the public scene share page.
- **Codex entry edit page shows where each entry is referenced.** The edit form's sidebar gains a
  **"Referenced in scenes"** card listing every scene whose contents match the entry's name or an
  alias, in event-timeline order `(event_datetime, id)`; scenes with no assigned event sort last and
  are labelled "No event assigned". Each row links to the scene's edit page. The aliases field gains
  help text explaining that matching is case-sensitive, whole-word, ignores aliases under 3
  characters, and can be ambiguous when aliases overlap — so a writer can understand why a name
  silently never links. Read-only view of the derived `scene_codex_entry` cache; no AJAX, refreshed
  on save.
- **Codex entry saves now recompute scene references.** Creating a codex entry always runs
  `SceneReferenceMatcher::syncProject()` (a new entry's name/alias set is trivially new), so a scene
  whose contents already mention the entry links immediately with no scene re-save. Editing an entry
  runs the project-wide rescan **only when its matching terms (name plus aliases) actually change** —
  an unrelated edit (new cover image, description tweak) skips the O(scenes) recompute. The
  before/after comparison and the rescan both run inside the entry's existing `DB::transaction`, so
  aliases and references stay atomic. Entry deletion needs no code: `cascadeOnDelete` already drops
  the pivot rows.
- **Scene saves now record codex references.** Creating or updating a scene runs
  `SceneReferenceMatcher::syncScene()` after the row is saved, so the `scene_codex_entry` pivot
  always reflects which codex entries the scene's current `contents` reference (a full resync — no
  stale rows). No "did contents change" skip: a scene save always recomputes its own references,
  mirroring the adjacent `mentionedEvents()->sync()` call.
- **Project import regenerates scene ↔ codex references.** `ProjectImporter::run()` recomputes the
  `scene_codex_entry` cache once via `SceneReferenceMatcher::syncProject()` after the graph-import
  phases finish and before the import is marked completed — the archive never carries this derived
  data, so an imported project ends up with exactly the references a native save would have produced
  (including overlapping-alias links to every matching entry). The hook is not a fifth import phase:
  it runs at the post-loop fall-through reached exactly once per finishing import, and being a full
  idempotent resync it is safe to retry on a resumed run. Confirms the exporter still writes no
  reference data.
- **Scene ↔ Codex reference matcher.** New `App\Services\SceneReferenceMatcher` computes which
  codex entries a scene's `contents` reference — a whole-word, **case-sensitive**, Unicode-aware
  (NFC-normalized) match of each entry's `name` and eligible aliases (aliases shorter than 3
  characters are ignored), persisted as a full `sync()` into the `scene_codex_entry` pivot. Hyphen
  is part of the word ("Jean" does not match inside "Jean-Luc"), and malformed UTF-8 in a scene is
  logged and skipped rather than allowed to block the save. Declares the previously-implicit
  `ext-intl` requirement in `composer.json`. Controller wiring and UI arrive in later tasks.
- **Scene ↔ Codex reference links (data model).** New `scene_codex_entry` pivot table
  (plain link table, composite PK, `cascadeOnDelete` on both FKs — matching the
  `codex_entry_tag` / `event_scene` convention) with `Scene::codexReferences()` and
  `CodexEntry::referencingScenes()` relations. This is the persisted, derived cache of
  "which codex entries a scene's contents reference"; matching logic, controller wiring,
  and UI arrive in later alias-references tasks.

### Fixed

- **English demo data (`MelusineSeederEn`) was missing the aliases scenes actually use.** Mélusine's
  entry only had the accented `Mélusine` name and `Melusina`/`The Serpent Lady`/`Lady of Lusignan`
  aliases, but every scene spells the name **without** the accent (`Melusine`) — a different letter,
  not a normalization difference, so it could never match. Raymondin's entry had `Raymond` as an
  alias, but `Raymond` is a substring of `Raymondin` (the spelling scenes use), not a separate word,
  so whole-word matching correctly refused it. Added `Melusine` and `Raymondin` as aliases so the
  demo project's own scenes link the way a reader would expect (`codex:sync-references` picks up
  existing seeded scenes once these land).
- **French and Italian demo data had the same gap for their Raymondin/Raimondino entries.**
  `Raymond`/`Raimondo` are substrings of the `Raymondin`/`Raimondino` spelling the French and
  Italian scenes actually use, so whole-word matching never linked them (same class of bug as the
  English fix above; the French/Italian Mélusine entries were unaffected — their scenes already
  spell the accented name consistently). Added `Raymondin` to `MelusineSeederFr` and `Raimondino`
  to `MelusineSeederIt`.

## Earlier (shipped before 2026-07-14)

These entries predate the dated-section convention above; their individual ship dates are
in git history (`git log -S "<entry text>" -- CHANGELOG.md`). Grouped by change type.

### Added

- **Project import.** **Admin → Export & import → Import** now reads an export `.zip` back into a
  brand-new project owned by the importing user (the tab previously just said import was "coming soon").
  Import reconstructs the full graph from the archive's lossless `data/` layer — Project, Acts/Chapters/
  Scenes, the Timeline (plotlines + events), and the Codex (entries, aliases, tags, attributes,
  event-anchored attribute values, and media) — remapping every archived id onto fresh rows and replaying
  `position` verbatim. The upload is treated as untrusted: a six-check security gate
  (`ArchiveValidator` — zip validity, zip-slip, an allow-listed arborescence, manifest version, JSON
  shape, and content-sniffed media types) plus a reject-on-violation content sanitizer run before
  anything is written, and the auto-created main plotline / Start/End bookends are reconciled rather than
  duplicated. A name collision only ever renames the new project (timestamp suffix); import never merges
  or overwrites. The import is checkpointed per phase onto an `Import` tracking record so a crashed import
  can be **resumed** or **discarded**, runs synchronously by default (no queue worker required) with an
  opt-in `run_in_background` toggle on the new `ImportSetting` singleton, and — like export — is behind
  the admin gate with resume/discard guarded by a per-owner `ImportPolicy`. See
  `documentation/architecture.md` → *Static site import*.
- New `x-delete-button` component: the labelled, full-form sibling of `x-icon-delete-button`
  (`<form>` + `@csrf` + `@method('DELETE')` + native `confirm()` dialog around a
  `x-button variant="danger" :icon="true"`). Replaces 9 hand-written `onsubmit="return confirm(...)"`
  delete forms at the bottom of entity edit pages (Act, Chapter, Plotline, Scene, Event, Project,
  Codex entry, Codex attribute, and the scene share-link "Revoke" action).

### Changed

- Migrated the 9 admin (`admin/settings`, `admin/database`, `admin/appearance`, `admin/data`) and
  profile (`profile/partials/*`) settings panels from the hand-rolled Breeze
  `p-4 sm:p-8 bg-white shadow sm:rounded-lg` panel to `x-card`, using its `header` slot for each
  panel's title + description. `profile/edit.blade.php` no longer wraps each `@include` in its own
  panel `<div>` — each partial now owns its `x-card` directly. Also resolved two more of the
  previously-unmigrated `x-heading` gaps: the profile/admin panel titles now use level 3, the
  `admin/data` Epub-export subsection heading uses level 4, and the public "share link expired" page's
  `<h1>` uses level 1.
- Migrated every call site off the legacy `x-primary-button` / `x-danger-button` / `x-secondary-button`
  Breeze components onto `x-button` (`variant="primary|secondary|danger"`), then deleted the three
  legacy components. Gave `x-button` an `icon` prop (leading floppy-disk icon for `primary`, trash for
  `danger`) to carry over the `:icon="true"` behaviour those components had gained. `x-button` defaults
  `type` to `submit` for every variant (the old `x-secondary-button` defaulted to `type="button"`), so
  every migrated secondary "Cancel"/"Copy"/"Regenerate"-style button that relied on that implicit
  default now sets `type="button"` explicitly to keep its original non-submitting behaviour.
- Migrated page-header and section titles across the app to the shared `x-heading` component instead
  of hand-styled `<h1>`–`<h6>` tags, for the instances whose existing classes matched one of the
  component's scale levels exactly. Redefined the scale's level 2 to `text-xl font-semibold
  text-gray-800` (the app's actual page-header size) and added a new level 6 for the smallest
  uppercase group labels; levels 3–5 shifted down accordingly. See `documentation/ui-components.md`
  for the full scale and why level 2 is pinned to the header-title size. A few headings with no exact
  match to any level (the profile/admin `text-lg font-medium text-gray-900` panel headings, the Story
  overview's act/chapter anchor headings, and a couple of one-off labels) were intentionally left
  unmigrated rather than force a visual change.
- Consolidated edit/delete/save/remove/close/download controls across the site onto a small set of
  shared button components instead of ad hoc text buttons. Row-level actions (list rows, the Codex
  attribute-timeline period editor) are small outline icon-only buttons; main entity actions (Save,
  Delete/Revoke on each entity's edit page) are full-size labelled buttons with a matching icon
  (`x-primary-button`/`x-danger-button` gained an `:icon` prop). The Codex attribute-timeline period
  "Remove" button now reuses `x-icon-delete-button` instead of a separate labelled danger button.
- Codex entry edit page: added a `border-t` divider and extra spacing above the Attribute timeline
  section so it reads as its own section rather than a continuation of the entry form.
- Codex entry create/edit form now uses a two-column layout (main content 9/12, a Cover-above-Tags
  sidebar 3/12) instead of three columns. Reference images and reference files moved out of the
  sidebar into a full-width tabbed block ("Reference images" / "Reference files") above the Save
  button. Reference image thumbnails are clickable and open in a lightbox; reference files show a
  "View" trigger that previews the file in a modal iframe alongside an explicit "Download" link.

### Added

- Installed `secondnetwork/blade-tabler-icons` (Composer, vendored SVGs — no CDN, works airgapped)
  and migrated every icon button on the site to it. New shared components `x-icon-save-button`
  (outline, for row-level saves), `x-icon-close-button` (outline, with a `light` variant for
  on-dark overlays), and `x-icon-download-button` (outline).
- Epub export from **Admin → Export & import → Export**, alongside (not replacing) the existing
  `.zip` export. A signed-in project owner picks a project and downloads a standard `.epub` file
  built by `App\Services\EpubExporter` via `rampmaster/phpepub`. Acts render as their own divider
  page ("Act N" + the act's name, blank names omitted, no description); Chapters start new pages
  ("Chapter N: title") with their Scenes' Markdown compiled to clean HTML and joined by `<hr>`, no
  per-scene titles or descriptions. Chapters with zero Scenes, and Acts left with zero surviving
  Chapters, are silently omitted from the book, TOC, and spine; a project with nothing left after
  that filtering fails with a clear error instead of downloading a broken file. Every export opens
  with a story title page (the Project's name, centered and set larger) followed by an in-book
  table of contents page — a real, readable spine page distinct from the reader's own EPUB 3 nav
  chrome — listing every surviving Act with its surviving Chapters nested underneath, each linking
  straight to its page; both front-matter pages precede the story itself in reading order. Act
  headings (the "Act N" number and the act's name) are likewise centered and set larger than body
  text. The table of contents (both the nav and the in-book page) is two-level (Acts nesting their
  Chapters). A dedicated, epub-only CommonMark pass
  converts `--`/`---` to en/em dashes, `...` to an ellipsis, and straight quotes to curly quotes —
  `Scene::renderedContents` (used by the Story overview, share page, and the existing `book/`
  export) is deliberately untouched. `Project` gained six new optional book-metadata fields
  (`language` — required, defaults `en`; `author`; `publisher`; `rights`; `isbn`, validated as a
  real ISBN-13 via the new `App\Rules\ValidIsbn`; and `cover_image`, uploaded via the Project edit
  form reusing `CodexMediaRules`' validation and the `public` disk), editable from the Project edit
  screen and mapped onto the epub's OPF metadata (Dublin Core fields, a generated
  `urn:imagoldfish:project:{id}` identifier plus a second `urn:isbn:` identifier when set, and
  EPUB accessibility metadata — `accessibilityFeature`/`accessMode`/`accessibilitySummary`). Every
  generated document declares `lang` from `Project.language`. Generated OPF documents are
  structurally validated against a RelaxNG schema vendored from the `epubcheck` project (converted
  from its `.rnc` sources at build time — no JVM at runtime); content/nav XHTML is validated for
  well-formedness. The export page links to the official
  [epubcheck](https://www.w3.org/publishing/epubcheck/) tool for authors who want full conformance
  verification. Authorization mirrors the existing export (`ProjectPolicy@view`, a foreign
  `project_id` 403s). See `.specs/shipped/2026-07/epub_export_v1/resolution-log.md` for the library
  research (the spec's originally-implied `grandt/phpepub` is dead since 2016) and several
  epub-library quirks worked around along the way.
- Portable toolchain & shell conventions in [`.claude/conventions/tooling.md`](.claude/conventions/tooling.md),
  referenced by a single pointer line in `CLAUDE.md`. The rules select the shell by *tool
  availability* (never by OS name — no shell is privileged), forbid carrying one shell's syntax
  into the other's tool (the platform-independent rule that prevents the cross-shell bug class),
  map lockfiles to package managers, and single-source canonical commands (test = `composer test`).
  This is Claude-workflow tooling only — no application code, routes, or runtime behavior changes.
  (A machine-local env cache + SessionStart hook were explored alongside these rules and then
  dropped as over-built for the payoff; see the feature's `resolution-log.md` and git history.)
- Project export to a downloadable `.zip` from **Admin → Export & import → Export**. A signed-in
  user picks one of their own projects, chooses whether to include images & files, and downloads an
  archive built by the HTTP-agnostic, async-ready `App\Services\StaticSiteExporter`. The archive is
  two-layered. The **`data/`** layer is a lossless, machine-readable copy (source of truth for a
  future import): the Story tree (project + acts → chapters → scenes), the Timeline (plotlines +
  events, including the seeded main plotline and Start/End bookends), and the Codex (entries with
  aliases/tags/attribute-values-over-time/media, plus flat attribute-definition and tag lists), every
  entity a `<id>-slug` directory of JSON + raw field files. Content fields are stored verbatim — never
  re-rendered or re-sanitized. The **`book/`** layer is the human reading version: a TOC `index.html`
  plus one compiled HTML page per chapter (scene `contents` rendered Markdown → HTML, joined by `<hr>`,
  with prev/next reading navigation crossing act boundaries) — the only place the export renders
  Markdown. A top-level **`README.md`** greets whoever opens the zip: project name, export date, the
  description as plain text, and a note pointing humans to `book/` and machines to `data/`. Media
  **bytes** are governed by the "Include images & files" toggle; media metadata is
  written regardless. Authorization walks `ProjectPolicy` on top of the admin gate, so a foreign
  `project_id` 403s rather than silently exporting another user's project. `ext-zip` is now a declared
  `composer.json` dependency. The export/import format contract lives in
  [`documentation/export-format.md`](documentation/export-format.md).
- Admin Configuration area (`/admin`): a settings hub with a left sidebar switching between four
  sections, every route behind `auth` plus a single `access-admin` Gate (returns true for any
  authenticated user — the deliberate continuation of the `CrawlerSetting` no-`is_admin` posture,
  encoded once on the route group so it can be tightened later without touching controllers). The
  user-dropdown entry now reads **"Configuration"** and lands on General settings. Sections:
  **General settings** (hosts the search-engine visibility form, see *Changed*), **Appearance &
  accessibility** (placeholder for future graphical/accessibility options), **Export & import** (an
  accessible inline-Alpine tab interface — WAI-ARIA `tablist`/`tab`/`tabpanel`, roving tabindex,
  arrow-key navigation — stubbed "coming soon"; the backup/restore engine is a separate future
  spec), and **Database configuration** (read-only display of the active connection — driver,
  database name/path, host; the password is whitelisted out in the controller and never reaches the
  view). Shared `<x-admin-layout>` + sidebar partial reuse the documented nav active-state pattern
  (`aria-current="page"`, never colour-only).
- Active-state highlighting for the **desktop** primary-nav dropdowns (Timeline, Codex, Story) and
  their collapsed trigger buttons: the item matching the current route now renders with the
  light-panel highlight (`bg-aqua-50 text-navy-900 font-semibold`) and carries `aria-current="page"`,
  and a trigger reflects when any of its child routes is active (`text-white border-flame-500`,
  matching the `x-nav-link` active look). Previously only the responsive (mobile) menu highlighted;
  the desktop dropdowns highlighted nothing.
- Friendly empty states on the index pages. The shared `x-table-empty` component now renders two
  distinct messages instead of a single bare "no results" row: a genuinely empty collection shows
  "No :items yet." with a primary button pointing at the create action, while a collection hidden by
  an active search/filter shows "No :items match your search or filters." (the toolbar's *Clear*
  link is the way back). Wired into the Codex (characters/locations/organizations) and the
  events/acts/chapters/scenes indexes.
- Dedicated feature tests for `ActController`, `ChapterController`, and `StoryController`
  (`ActTest`, `ChapterTest`, `StoryTest`), closing the last of the coverage gaps noted in
  `CLAUDE.md` (Scenes were covered earlier by `SceneTest`). Each covers the index, the full CRUD
  surface, project authorization (owner succeeds, non-owner gets 403 on read and every write path),
  validation failures, the auto-assigned `position` invariant, and the move-up/move-down sibling
  swap (including that it is scoped to the correct parent and is a no-op at the ends). `StoryTest`
  additionally asserts the read-only overview renders the nested act → chapter → scene tree in
  `position` order.
- Dedicated feature tests for `PlotlineController` and `EventController` (`PlotlineTest`,
  `EventTest`), previously only covered indirectly through `ProjectTest`. Each covers the full
  CRUD surface, project authorization (owner succeeds, non-owner gets 403 on read and every write
  path), and the domain invariants: the `is_main` plotline and the `is_fixed` Start/End bookend
  events are un-deletable (403), and `WithinEventWindow` is enforced on both the event store and
  update paths.

- Hidden from crawlers: a global toggle to hide the whole site from search engines, delivered as
  a dynamic `/robots.txt` plus a `noindex, nofollow` meta tag on every public-facing layout. The
  policy is one application-wide `CrawlerSetting` singleton (global — owned by no `Project`, read
  via `CrawlerSetting::current()`, lazily seeded from `config/crawlers.php`, default **hidden**).
  `RobotsTxtGenerator` builds robots.txt from the setting: when hidden it emits one allow-group
  per whitelisted crawler (a user-agent whitelist, one term per line, validated line-safe) then a
  catch-all `Disallow: /`; when off it allows everyone. The `x-robots-meta` component is the single
  source of the meta string, wired into `app`/`guest`/`welcome` (toggle-governed) and `public`
  (forced — shared scenes stay hidden regardless). An authenticated settings screen
  (`/settings/crawlers`, "Site settings" in the nav) edits the toggle and whitelist; it is the one
  deliberate departure from project-scoped authorization — any authenticated user may edit the
  global setting (no `is_admin` role).
- Scene sharing (foundation): two nullable columns on `scenes` — `share_token` (unique, stored
  raw) and `share_expires_at` — backing one revocable public share link per scene. `Scene` gains
  a `share_expires_at` datetime cast and two helpers: `isShared()` (token set **and** expiry in
  the future) and `shareUrl()` (public URL by route name, or null when unshared). Neither column
  is mass-assignable — the token is set explicitly in the controller. Share-link lifetimes come
  from a `config/sharing.php` whitelist (`scene_link_durations`: 24 hours / 7 days / 30 days) that
  the owner picks from, never a hard-coded literal. Controllers, routes, and views follow in later
  tasks.
- Scene sharing (public page): an unauthenticated, read-only view of a shared scene at
  `GET /shared/scenes/{token}` (`shared.scenes.show`), served by `SharedSceneController@show`
  **outside** the `auth` group — the opaque token is the only gate (no policy; documented as the
  single deliberate exception to "every action authorizes"). An unknown token returns 404 and an
  expired/revoked token renders a friendly branded 410 page (`shared/scenes/expired.blade.php`)
  rather than a bare error, checked via `Scene::isShared()` so a leaked-but-expired URL is inert.
  The page uses a dedicated no-nav `<x-public-layout>` whose `<head>` carries a
  `noindex, nofollow` robots meta, and renders only the scene title (Arabic `chapter.position`,
  em-dash), the description (collapsed card via `x-rich-text`) and the Markdown `contents` — the
  scene's `notes` are **never** exposed. The owner edit-page UI that generates these links follows
  in the next task.
- Scene sharing (owner management): `SceneShareController` with `store` (generate/rotate the link)
  and `destroy` (revoke it), exposed as authenticated `POST`/`DELETE /scenes/{scene}/share`
  (`scenes.share.store` / `scenes.share.destroy`). `StoreSceneShareRequest` validates the chosen
  duration against the `config('sharing.scene_link_durations')` whitelist via `Rule::in`, and both
  the request and controller authorize by walking up to the owning project (`ProjectPolicy@update`
  — non-owners get 403). The token is `Str::random(48)`, set explicitly (never mass-assigned);
  re-posting `store` rotates it and resets the expiry, invalidating the previous URL. The public
  view route and the edit-page UI that posts to these endpoints follow in later tasks.
- Scene sharing (owner UI): a "Share this scene" card on the scene edit page with two states driven
  by `Scene::isShared()`. Unshared shows a duration `<select>` (populated from
  `config('sharing.scene_link_durations')`, default preselected from `scene_link_default_duration`)
  and a "Generate share link" button posting to `scenes.share.store`, surfacing `duration`
  validation errors and preserving `old()`. Shared shows the public URL in a read-only field with an
  accessible Copy button (inline Alpine `navigator.clipboard.writeText` + "Copied!" confirmation),
  the expiry both absolute and relative, a Regenerate button (re-POST `store`) and a Revoke button
  (`DELETE scenes.share.destroy`). Reuses existing components only — no new component or route.
- Rich-text (WYSIWYG) editing for the app's free-text fields. A Tiptap-backed editor component
  (`x-wysiwyg`) with both an always-visible formatting toolbar **and a Notion-style `/` slash
  command menu** (headings, bold/italic/underline/strike, lists, blockquote, inline/block code,
  links, horizontal rule) replaces the plain `<textarea>` on every rich field, as **progressive
  enhancement** over a real textarea (a JS-off submit still works and `old()` repopulates on
  validation failure). Rich-HTML content is sanitized **server-side on write** by
  `App\Services\HtmlSanitizer` (HTMLPurifier, a strict allow-list centralized in
  `App\Support\RichTextFields`) via per-field set-mutators, so the DB never holds unsafe HTML; it
  is rendered back only through the `x-rich-text` component (`x-rich-text-excerpt` gives index
  tables an escaped, tag-stripped preview). **`Scene.contents` uses the same editor in Markdown
  mode** (`@tiptap/markdown`): it gains the WYSIWYG authoring experience while its stored value
  stays clean CommonMark (`ValidMarkdown` + `Str::markdown()` unchanged). The slash menu reuses
  `@tiptap/suggestion` + its bundled `@floating-ui/dom`, so it needs no extra dependency. Image
  upload is intentionally **not** in this version. Documented in
  [`documentation/rich-text.md`](documentation/rich-text.md).
- Codex: a project-scoped reference aggregate for the story's **characters, locations, and
  organizations**. All three share one `codex_entries` table keyed by a `CodexEntryType`
  enum and one `CodexEntryController`, with the kind carried as a `{type}` route segment
  (`characters`/`locations`/`organizations`). Each entry has **aliases**, flat **tags**
  (reusable per project), Markdown **descriptions**, and **media** (a single cover plus
  reference images/files on the `public` disk; cover is the `codex_media` row with
  `collection = Cover`, not a FK). **Temporal attributes** — attribute definitions
  (`codex_attributes`, with an `applies_to` array of entry types) whose values form a
  **start-anchored step function**: each period runs from its anchoring event until the next
  (or the *End* event), so the timeline stays gap-free and a value can be resolved "as of"
  any moment. The new `App\Services\AttributeTimeline` (the project's first `app/Services`
  class) owns resolution and gap-free upserts/removals; `App\Services\CodexMediaService`
  owns file storage, the single-cover rule, and on-disk cleanup. Scene and event pages gain
  **"as of" panels** showing each entry's attribute values at that moment (e.g. a scene during
  *Back to class* shows the character's hair as black). Authorization walks up to the owning
  `Project` (no new policies); a Codex nav dropdown sits between Timeline and Story.
  `MelusineSeeder` seeds a demo set (Mélusine with aliases/tags and a hair-color timeline,
  Raymondin, the Castle of Lusignan, and the House of Lusignan) by calling the timeline
  service directly, since seeding runs with model events disabled.
- Scene ↔ Event links, two relationships. **"Happens during"** — an optional
  `scenes.event_id` foreign key (`nullOnDelete`) placing a scene during a single event;
  chosen on the scene form via a select or an inline "New event" quick-create (auto-attached
  to the Main plotline). **"Mentions"** — an optional `event_scene` many-to-many pivot, edited
  as a checkbox list. Unassigned scenes (no "happens during" event) are flagged with a red
  border on the scenes index and Story overview. Deleting an event unassigns its scenes (via
  the FK) and drops its mention rows (pivot cascade); the event edit page lists the scenes
  happening during / mentioning it. The scene form's "mentions" input is a searchable,
  chip-based event picker (`x-event-picker`, client-side Alpine filter by name/date) rather
  than a checkbox list, so it scales to projects with many events.
- Bookend timeline events: every project is auto-created with two fixed events, "Start"
  (first day of year 0001) and "End" (first day of year 3000), attached to the main
  plotline. Both carry `events.is_fixed` and cannot be deleted (delete button hidden in
  the events index/edit views, `abort_if` guard in `EventController@destroy`), mirroring
  the un-deletable main plotline. `MelusineSeeder` creates them manually since seeding
  runs with model events disabled.
- Project theme palette registered in `tailwind.config.js`: `ocean` (#219EBC),
  `aqua` (#8ECAE6), `navy` (#023047), plus `sun` (#FFB703) / `flame` (#FB8500)
  accents, each as a full shade scale.
- `x-table` component family for the striped, sortable index tables (plotlines, events,
  acts, chapters, scenes): `x-table` (card + `<table>` + `head` slot), `x-table-heading`
  (non-sortable header cell), `x-table-row` (striped body row), and `x-table-empty`
  (no-results row). Documented in [`documentation/ui-components.md`](documentation/ui-components.md).
- Reusable UI component library (Blade components in `resources/views/components/`):
  `heading` (unified `<h1>`–`<h6>` scale), `button` (variant/size, renders `<a>` or
  `<button>`), `card`, `badge`, `alert` (dismissible, contextual variants),
  `breadcrumbs` (data-driven), `tooltip`, `popover`, and `dialog` (header/body/footer
  modal built on the existing `modal` shell). Documented in
  [`documentation/ui-components.md`](documentation/ui-components.md).
- Scene status workflow: scenes now carry a `status` (`Draft`, `To Proofread`,
  `To Edit`, `Final`) backed by the `SceneStatus` enum, plus a freeform `notes`
  field. Status renders through a reusable `scene-status-badge` Blade component on
  the scene create/edit/index screens and the story overview.
- Story Overview page (`projects.story.index`) combining the full act → chapter →
  scene tree on one read-only page, with a collapsible table of contents and scene
  contents rendered as Markdown.
- Feature tests for the scene resource (`tests/Feature/SceneTest.php`) covering CRUD,
  authorization, validation, and position auto-assignment; model factories for
  `Act`, `Chapter`, and `Scene`.
- Project coding guidelines (`.claude/guidelines.md`) and a `documentation/` folder
  (architecture, code style, best practices, glossary).
- Scene sharing (polish): the expired/revoked 410 page now shows a "This link expired X ago"
  relative-time hint (`share_expires_at->diffForHumans()`). The controller passes **only** the
  expiry timestamp — never scene content — and the hint is omitted when no expiry is recorded, so
  no data leaks. Covered by `SceneShareTest`.
- Promoted `league/commonmark` to a direct dependency in `composer.json`'s `require`. The Story
  overview renders `Scene.contents` via `Str::markdown()`, which relies on it; it was only present
  transitively, so a dependency prune could have silently broken Markdown rendering.

### Changed

- Extracted three misplaced/duplicated helpers to the home the architecture already implies (pure
  refactors, no behaviour change). (1) The move-up/move-down position-swap logic that was copied
  verbatim across the Act/Chapter/Scene controllers now lives once in the `HasSiblingPosition` model
  trait (each model declares its `siblingScopeColumn()`; the two-row swap runs in a transaction), and
  the controllers call `$model->moveUp()` / `moveDown()`. (2) The HTML-to-plain-text converter that
  had drifted into `StaticSiteExporter` moved to `App\Support\RichText::toPlainText()` beside the
  rest of the rich-text module. (3) The `Str::markdown($scene->contents ?? '')` render duplicated in
  three views (Story overview, public share view, book export) is now the single `Scene::renderedContents`
  accessor, so the null-guard and renderer choice have one home. Each moved helper gained a direct
  unit/feature test at its new home.

- The search-engine visibility ("hidden from crawlers") settings screen moved out of its standalone
  `/settings/crawlers` route into **Admin → General settings** (`/admin/settings`), under a "General
  settings" heading. The form, validation (`UpdateCrawlerSettingRequest`), and the `CrawlerSetting`
  singleton are unchanged — only the route (`crawler-settings.*` → `admin.settings.*`), the
  controller (`CrawlerSettingController` → `GeneralSettingsController`), and the wrapping layout
  changed. The old `/settings/crawlers` route was removed (no redirect alias); its behavioural tests
  were relocated into `AdminConfigurationTest`.

- The "no happens-during event" affordance on scenes is now explained: the red left border and the
  "Unassigned" badge on both the scenes index and the Story overview carry a `title` tooltip
  ("This scene has no “happens during” event yet."). Previously the red border's meaning was
  undocumented in the UI.

- The `x-dropdown-link` component now accepts an optional `active` prop (default `false`), mirroring
  `x-nav-link` / `x-responsive-nav-link`: pass `:active` to get the highlight plus `aria-current="page"`.
  Untouched call sites (the Settings dropdown) are unaffected. The nav's route-match expressions were
  consolidated into a single `@php` block at the top of the `navigation.blade.php` project guard — one
  source of truth reused by the desktop triggers, the desktop dropdown items, and the responsive menu
  (no `Nav` support class or view composer for a styling tweak).
- `ProjectTest` was trimmed to project-scoped concerns (dashboard, project CRUD/authorization,
  and the project-creation invariants that seed the main plotline and the Start/End bookends). Its
  plotline- and event-controller cases moved to the new dedicated `PlotlineTest` / `EventTest`, so
  each controller's coverage now lives in one place rather than being duplicated.
- Developer tooling: `.specs/` is now organised by lifecycle status — each feature folder lives
  under `.specs/<status>/<name>/` (`draft` / `expanded` / `planned` / `shipped`) and moves between
  those subfolders as it advances. The pipeline skills (`mp-spec-expander`, `plan-tasks`,
  `ship-plan`) and the `plan-implementer` agent locate a feature by name via the glob
  `.specs/*/<name>/`, and each stage now moves the folder in the same step it stamps the new
  `status:` frontmatter. `tests/Unit/SpecsStatusConsistencyTest` reconciles the two representations,
  failing `composer test` if a `spec.md` `status:` disagrees with its status folder (this caught the
  `hidden_from_crawlers` spec, which had shipped but was still stamped `planned`).
- Bookend **Start / End** event datetimes are now **editable** (previously frozen). In their
  place the bookends form a **containment window**: every non-fixed event must satisfy
  `Start ≤ event_datetime ≤ End` (inclusive), and a bookend edit may not swallow an existing
  event — Start can't move past the earliest regular event or reach End, and End is the mirror.
  A single rule (`App\Rules\WithinEventWindow`) enforces this on every event write path
  (`StoreEventRequest`, `UpdateEventRequest`, and the Scene inline `new_event_datetime`), and the
  datetime inputs carry `min`/`max` hints (`EventController::datetimeBounds()`,
  `Project::earliestRegularEvent()` / `latestRegularEvent()`). Because Start stays the earliest
  `is_fixed` event, the codex attribute-timeline baseline still resolves to the same row. The
  default Start moved from year 0000 to **year 0001** (Laravel's `date` rule floors at year 1 via
  `checkdate()`); End stays year 3000.
- Free-text description fields (`Project`, `Act`, `Chapter`, `Plotline`, `Event`, `Scene`,
  `CodexEntry`) and `Scene.notes` are now **rich HTML** rather than plain text — authored in the
  new WYSIWYG editor, sanitized on write, and rendered with formatting. The codex `description` in
  particular is **no longer Markdown**; it is now rich HTML like the others. **`Scene.contents` is
  unchanged** — it stays Markdown-only (`ValidMarkdown` + `Str::markdown()` on the Story overview),
  the one deliberate carve-out from the rich-text feature.
- Index tables (plotlines/events/acts/chapters/scenes) now render each row's
  description as small muted text beneath the title instead of a separate
  Description column, and the ordered lists (acts/chapters/scenes) expose their
  `#` position column as a sortable header.
- Index-row edit/delete icon buttons (`x-icon-edit-link`, `x-icon-delete-button`)
  are now outlined: transparent fill with a colored border and matching text —
  `navy-500` for edit, `red-600` (danger) for delete.
- Reskinned the app chrome to the theme palette: the Breeze default indigo accent
  (focus rings, links, active nav, `badge` primary/indigo variants) is now `ocean`;
  primary buttons fill with deep `navy` (higher contrast against their white label);
  and the active-navigation indicator uses the `flame` orange accent. Body text and
  semantic colors (info/success/warning/danger) are unchanged.
- Banded the app header: the top navigation bar is now `navy` (with its logo, links,
  dropdown triggers, and mobile menu lightened to `aqua`/white for contrast) and the
  page-heading bar below it is `ocean-800` (its heading text and back/edit links
  lightened to white/`aqua` via wrapper-scoped selectors in the app layout).
- Index table headers (`<thead>` on plotlines/events/acts/chapters/scenes, plus the
  `sortable-header` component) now use a `sun-400` background with `navy-900` cell
  text for strong contrast.
- Striped table rows are now `gray-100` (a step darker than the previous `gray-50`),
  set once in the shared `x-table-row` component.
- Reordered scenes in the story overview via AJAX (no full page reload).
- Restyled the story overview typography and act headings.
- Consolidated the `MelusineSeeder` chapters into fewer, denser chapters.
- Codex bookend events (Start/End) now have their `event_datetime` **frozen**: editing an
  `is_fixed` event can change its title/description/plotlines but not its date
  (`UpdateEventRequest` applies `prohibited`; the edit form hides the input). This keeps the
  Start/End sentinels that anchor the attribute timeline from being re-ordered.
- Removing an attribute period's Start baseline while later periods exist now returns a
  `403` (`abort_if`) instead of surfacing a `RuntimeException`.
- Codex route constraints, `CodexEntryType::fromRouteKey()`, and the navigation dropdown are
  all **derived from `CodexEntryType`** — no hardcoded `characters|locations|organizations`
  string lists remain, so adding a codex type no longer means editing five scattered lists.
- The codex navigation now highlights the **current** codex type (characters/locations/
  organizations) rather than always highlighting the first link.
- The codex index tag-filter dropdown hides tags that have no entries (`whereHas('entries')`).
- The attribute-definition form shows a hint that narrowing `applies_to` strands existing
  values for the removed entry types (non-destructive; they simply stop being shown).

### Fixed

- Moving a chapter to a different act via the edit form now works. The edit view offered an act
  selector and `ChapterController::update()` intended to honour it, but `act_id` is deliberately not
  in `Chapter::$fillable`, so the mass-assignment silently dropped it and the chapter never moved.
  Update now reparents through the `act()` relationship (keeping `act_id` guarded); regression
  covered by `ChapterTest::test_a_chapter_can_be_moved_to_another_act_in_the_same_project`.
- `php artisan db:seed` can now be re-run against a populated database: the admin
  user is only created when missing, instead of aborting on the `users.email`
  unique constraint before `MelusineSeeder` (already idempotent) was reached.
- The Home/Timeline/Story navigation menu no longer disappears on the event
  edit page: the layout's `$project` resolution chain now also resolves from the
  `event` route parameter (both the desktop bar and the responsive menu).
- The gap-free attribute-timeline invariant is now enforced on the period-store endpoint:
  `AttributeTimeline::upsertAt()` creates the Start baseline itself when a mid-timeline period
  is stored for a previously-unvalued (entry, attribute) pair, so `valueAt` stays total for
  `t ≥ Start` on every write path — not just entry creation.
- Codex media files no longer leak on disk when a **project** or **user account** is deleted:
  those deletions cascade at the database level and bypassed `CodexEntry`'s cleanup hook, so
  `Project` and `User` now have `deleting` hooks that purge the files (`purgeProject`) before
  the row cascade.
- Codex media disk I/O no longer runs inside the entry-save `DB::transaction`: file
  deletes/writes happen after commit, so a rolled-back save can no longer leave a media row
  pointing at a deleted file or orphan a written upload on disk.
- The attribute-timeline editor now renders validation errors (under `value` /
  `start_event_id`) and preserves typed input via `old()` on a failed save, which previously
  looked like Save silently doing nothing.
- Empty attribute values are now savable: `value` validates as `present`/`nullable` rather
  than `required`, so an empty baseline can be saved and a value can be cleared back to blank
  ("recorded as blank"), matching the create-form semantics.
- Codex media upload validation errors now render for **any** failing file index, not only the
  first (`reference_images.*` / `reference_files.*` instead of `.0`); the `x-input-error`
  component flattens the wildcard message bag.
- The codex entry form partial no longer runs a tag query at render time — the controller
  passes `projectTags`, keeping the query out of Blade.
