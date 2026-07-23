# Handoff — autosave-with-revisions

**Branch:** `spec/autosave-with-revisions`
**Plan:** `.specs/planned/2026-07/autosave-with-revisions/plan/` — all 16 tasks implemented
(`plan/implemented/`, only `00-overview.md` left in `plan/`).

## Status

Feature-complete. All 16 plan tasks done, full suite green (858 tests as of the last
task-16 run), `composer lint` clean. Committed locally in 3 commits, **not pushed**:

1. `Implement autosave-with-revisions tasks 13-16` — admin retention page, export/import
   revision sidecars, docs/changelog.
2. `Render revision compare as a side-by-side diff with entity context` — switched the
   compare view's diff renderer to `SideBySide`, folded the From/To metadata into the same
   card as a two-column header, fixed the History/Compare page headings to name the entity
   (`Compare — Project "Melusine" — Description`, not just `Compare — Description`).
3. `Guard the Melusine demo seeders against duplicate runs` — fixes the "6 projects
   instead of 3" bug (unconditional `Project::create()` in each seeder duplicated on a
   second `db:seed`), plus a regression test.

## Not yet done

- **Move the spec folder to `.specs/shipped/`** (plan is done, but the ship-plan
  folder-move step wasn't run this session).
- **Ship via `ship-pr`** — branch is ahead of `origin/spec/autosave-with-revisions` by 3
  commits, nothing pushed, no PR opened yet.

## Known gap — found in manual testing, not yet fixed

**The `localStorage` draft-recovery banner false-positives after a full DB reset**
(`migrate:fresh --seed`). Symptom: opening any autosave field immediately shows "A newer
version was saved elsewhere since these unsaved changes were made" right after a fresh
reseed, even though nothing was actually edited.

Root cause: `resources/js/autosave/field.js`'s draft mirror is keyed `entity:id:field`
(`storageKeyFor()`), with no signal for "the database was wiped and reseeded since this
draft was written." Autoincrement ids restart from 1 on a fresh seed, so a stale browser
draft from before the reset collides with an unrelated, freshly-seeded record at the same
id. `triageDraft()` (`resources/js/autosave/store.js`) then correctly does its job given
what it's told — `draft.baseHash` doesn't match the new record's hash, so it reports
`offer-compare-only` rather than trusting a bare restore. Not a server-side conflict (no
409 involved), and not actually wrong given the inputs — the gap is that a full reseed
isn't a case the draft key accounts for. Dev/local-reset-only in practice (a real user's
data is never bulk-reseeded), so it didn't block shipping.

- **Workaround for now:** clear the app's `localStorage` drafts manually — from devtools
  console on any page:
  ```js
  Object.keys(localStorage).filter(k => /^[a-z]+:\d+:/.test(k) || k.startsWith('new:')).forEach(k => localStorage.removeItem(k));
  ```
- **Suggested real fix, not yet implemented:** stamp each draft with something that
  changes on a reseed but not on a normal edit — e.g. a per-boot app/seed marker (a value
  in `config/app.php` or a dedicated install-id row bumped by `migrate:fresh`) written
  into the draft alongside `baseHash`, checked by `triageDraft()` before trusting the hash
  comparison at all; a mismatch there should `drop-silently` rather than
  `offer-compare-only`, since it means "different database entirely," not "someone else's
  newer save." Needs its own small task rather than a quick patch — `checkForDraft()`/
  `triageDraft()` is exercised by vitest (`store.test.js`/`field.test.js`) and the
  three-way triage contract is otherwise load-bearing (`handoff.md` §9.7 in the spec
  folder) — don't touch it without updating those tests too.

## How to resume

1. Decide: fix the `localStorage` draft-reset gap first, or ship as-is and file the gap
   as a follow-up spec/task.
2. If shipping as-is: move `.specs/planned/2026-07/autosave-with-revisions` →
   `.specs/shipped/2026-07/autosave-with-revisions` (folder + frontmatter `status:`, per
   `.specs/README.md`), then run `ship-pr` — confirm with the user before pushing/opening
   the PR (nothing has been pushed yet).
3. If fixing the gap first: it's a small, self-contained JS change (`field.js`/`store.js`
   plus their vitest specs) — no server-side work needed.

## Other context from this session

- Fixed the "6 projects instead of 3" dev-database bug: the Melusine demo seeders now
  guard against a repeat `db:seed` (see commit 3 above). Dev DB was reset via
  `migrate:fresh --seed` and now has exactly 3 projects.
- `identifier.sqlite` (an empty, untracked PhpStorm SQLite-tool artifact that was sitting
  at the repo root) is gone — cleaned up by a container restart/rebuild, not tracked or
  committed either way.
