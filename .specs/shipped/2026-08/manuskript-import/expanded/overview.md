# Overview

A local artisan command that turns a Manuskript **exploded** project directory into one new
`Project` owned by a chosen user: chapters, scenes (title + Markdown body) and characters, nothing
else.

> [!WARNING]
> Branch-only (`manuskript-import`), never merged to `master`. It is written against the specific
> projects being migrated, not against Manuskript in general. Two consequences the plan must honour:
> **no UI, no routes, no policies** (a console command has no authenticated user to authorize), and
> **no `documentation/` page** — this design folder is the documentation.

## Two corrections to `spec.md`

* Scene bodies **stay Markdown**. `Scene.contents` is the app's one Markdown carve-out
  (`App\Support\RichTextFields` docblock); it is never HTML and never routed through the
  sanitizer's write mutator. The spec's "convert Markdown to HTML" step does not exist.
* The import writes **no revisions at all**. Baselines are seeded lazily on the first edit
  (`RevisionRecorder::ensureBaseline()`), so an imported record simply has an empty history until
  its writer touches it.

## Acceptance criteria

* `php artisan manuskript:import <path> --user=<id|email>` on the real source tree creates one
  project with 21 chapters under a single act, every scene in source order, and 51 character codex
  entries.
* Chapter and scene order matches the source's numeric filename prefixes.
* Each character entry's description shows one heading per filled field, with `?` and empty fields
  absent.
* A source tree that is not a Manuskript project (no `MANUSKRIPT` marker) fails before anything is
  written.
* A failure at any point leaves no project behind.
* Re-running the command creates a second, independent project — no merging, no deduplication.

## Non-goals

Beyond `spec.md`'s list: no `--dry-run`, no resume, no progress bar, no queueing. The command is
expected to run in seconds against a local directory.
