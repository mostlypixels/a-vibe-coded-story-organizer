# Codex refactor — overview

Source spec: [`.specs/refactor_codex/spec.md`](../spec.md) — a code review of the Codex
feature (commit `c338ca9`, branch `codex`) with 9 ranked findings plus lower-priority notes.
This folder expands those findings into actionable design documents. Every location cited in
the source spec was re-verified against the current working tree; all line references below
are current.

## Problem statement

The Codex implementation is architecturally sound (thin controllers, `AttributeTimeline`
service, FormRequest auth mirroring, 73 test methods), but the review found:

- **Two confirmed invariant violations**: the gap-free timeline invariant is not enforced on
  the period-store endpoint (finding 1), and project/user deletion leaks every codex media
  file on disk (finding 2).
- **Two plausible runtime hazards**: disk I/O inside `DB::transaction` (finding 3) and
  Start/End resolution breaking if a bookend event's datetime is edited (finding 4).
- **Three UX/conventions defects**: swallowed validation errors + unsavable empty values in
  the timeline editor (finding 5), a DB query inside a Blade partial (finding 6), and
  per-index-0-only upload error rendering (finding 7).
- **Two reuse/altitude cleanups**: duplicated Start/End resolution (finding 8) and magic
  route-key strings (finding 9).

## Work areas (one document each)

| Document | Findings | Severity |
|---|---|---|
| [`timeline-integrity.md`](timeline-integrity.md) | 1, 4, 8 | correctness |
| [`media-lifecycle.md`](media-lifecycle.md) | 2, 3 | correctness |
| [`ui-fixes.md`](ui-fixes.md) | 5, 6, 7 (+ `old()` note) | correctness/UX, conventions |
| [`routes-and-navigation.md`](routes-and-navigation.md) | 9 | conventions/altitude |
| [`testing.md`](testing.md) | all | — |
| [`open-questions.md`](open-questions.md) | 4, 5 + deferred notes | — |

Findings 1, 4 and 8 are grouped because they share one root: "the project's Start event" is a
domain concept resolved by a fragile, duplicated query. Fixing 8 (a `Project::startEvent()`
helper) is the seam through which 1 and 4 are fixed.

## Goals

- Enforce the gap-free timeline invariant at the service layer so **every** caller inherits it.
- Never leak media files on disk, on any deletion path (entry, project, user account).
- Make disk I/O safe across the `DB::transaction` boundary in `CodexEntryController`.
- Make Start/End bookend resolution stable against event edits.
- Surface every validation error the timeline editor and media forms can produce.
- Align empty-value semantics between entry create (baseline `''` allowed) and period store
  (`value` currently `required`).
- Remove the Blade-side tag query and the hardcoded route-key string lists.

## Non-goals (explicitly deferred by the source spec)

- Lazy-loading / AJAX for the `CodexAsOfResolver` panels (fine at current scale).
- Behavior change when narrowing an attribute's `applies_to` (values stranding is
  non-destructive; only a form hint is worth considering — see `open-questions.md`).
- Orphaned-tag cleanup.
- Replacing the `RuntimeException` control flow in `removeAt` (see `open-questions.md` — cheap
  to fold in if desired).
- Any schema change beyond what finding 4's chosen resolution may require.
- New features; this is strictly a hardening/refactor pass on the existing Codex.

## Acceptance criteria

1. Storing a period at a non-Start anchor for a previously-unvalued (entry, attribute) pair
   creates the Start baseline in the same operation; `valueAt` stays total for `t ≥ Start`.
2. Deleting a project (and deleting a user account) removes every codex media file of every
   affected entry from `Storage::disk('public')`.
3. A media-form update that fails mid-way never produces a DB row whose file is missing;
   orphan files from failed uploads are cleaned up (or provably cannot occur).
4. Editing an `is_fixed` event cannot re-order the bookends (or Start/End resolution no longer
   depends on datetime ordering — per the decision in `open-questions.md`).
5. Every failed timeline-editor submit shows a visible error near the editor; an empty value
   can be saved (and cleared back to empty) on any period, matching baseline semantics.
6. `codex/partials/fields.blade.php` receives its tag list from the controller.
7. Upload validation errors render for **any** failing file index, not just `.0`.
8. `routes/web.php` and `layouts/navigation.blade.php` contain no literal
   `characters|locations|organizations` lists; both derive from `CodexEntryType`.
9. Every fix ships with a feature test that fails before the fix (per `.claude/guidelines.md`);
   `composer test` passes; `vendor/bin/pint` clean; `CHANGELOG.md` `[Unreleased] → Fixed/Changed`
   updated; `documentation/` and `CLAUDE.md` Codex sections updated where invariants change.
