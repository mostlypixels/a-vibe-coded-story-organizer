# Revision History Rework — standing issues

**What is still wrong with the shipped code.** Read this before extending the feature.

Distinct from `resolution-log.md` on purpose. That file is the *record of the work*: what
was decided, what deviated from the plan, what went wrong during a task and how it was
resolved — its entries stop being actionable once the task is done. This file is the
opposite: every entry here is **still true of the code on `master`**, and stays here until
someone fixes it or a decision retires it.

Each entry says what a **user** experiences, then what a developer needs to know. Severity
is about the writer using the app, not about how hard it is to fix.

Two kinds of entry:

* **Defects** — the code does something nobody chose. These are meant to be fixed.
* **Accepted costs** — a known consequence of a decision that was made with eyes open. These
  are not bugs and are not "to do"; they are here so nobody rediscovers them as surprises.

> [!NOTE]
> The defects below were found by **review, not by testing** — the suite was green and
> caught none of them. Three are now fixed (#1, #2, #4, on 2026-07-26), each with a
> regression test verified to fail before its fix. A fixed entry keeps its original text
> below the fix note, because *how it got there* is the part worth not repeating.
>
> **What has been seen in a browser**, via `/run-imagoldfish`: the entity-level History link
> and the sidebar's entity links (task 18); the compare screen; and the validation alert from
> #1, reproduced by temporarily lowering `config('revisions.caps')` to 10 and clicking
> *Revert to this*. Still only ever exercised by the test suite: the **conflict** alert (it
> needs a stale base hash, which cannot be produced by clicking), the undo flow, and the
> history list.

---

## Defects

### 1. A revert that fails validation says nothing at all — ✅ FIXED 2026-07-26

**Severity was: high.** `<x-revisions-layout>` now renders `$errors` in the same alert area
as the two flashes, under a sentence explaining that the value no longer passes today's
rules and that nothing was written. The validator is also given the field's headline as the
attribute name, so the message reads *"The Description must not be greater than…"* rather
than naming the internal `value` key. Covered by
`RevertRevisionTest::test_a_revert_that_fails_todays_validation_explains_itself_and_writes_nothing`,
which tightens the caps config mid-test to reproduce "the rules tightened since this was
saved", follows the redirect, and asserts against the rendered HTML. Verified to fail before
the fix.

Original entry:

**Severity: high.** The writer clicks *Revert to this*, the page comes back looking
identical, and there is no message, no change, and no explanation.

Reverting re-validates the old value against **today's** rules, which is correct — rules can
have tightened since the value was recorded, and an old value must not sneak in through a
door a normal save would have closed. But when that check fails, `Validator::validate()`
throws a `ValidationException`, Laravel redirects back with the message in `$errors`, and
**no view in `resources/views/revisions/` renders `$errors`**. The message is produced and
thrown away.

This is the same defect task 16 fixed for the *conflict* case, in the sibling path. It was
missed because the conflict path was the one the task named.

**Where:** `App\Services\RevisionReverter::restore()` (the `Validator::make(...)->validate()`
call); the missing render site is `resources/views/components/revisions-layout.blade.php`,
which now renders `session('error')` and `session('status')` but not `$errors`.

**Fix sketch:** render `$errors` in the same block as the two flashes. There is no form field
on these pages to attach a field-level error to, so it belongs in the shared alert area.

### 2. A revert is two writes with nothing tying them together — ✅ FIXED 2026-07-26

**Severity was: high.** `restore()`'s two writes are now wrapped in `DB::transaction`, as the
fix sketch below describes; task 17's outer transaction nests into it harmlessly, so the
single-field and whole-save paths can no longer fail differently. The re-validation stays
*outside* the transaction — a rejected value should not open one at all. Covered by
`RevertRevisionTest::test_a_failure_recording_the_revert_leaves_the_column_untouched`, which
mocks `RevisionRecorder::record()` to throw after the column write has already succeeded.
Verified to fail before the fix: the column moved and no history row existed, exactly the
defect described.

> [!NOTE]
> The `> [!IMPORTANT]` below said to fix this *before* task 17. That did not happen — task 17
> shipped first, so `revertSave()`'s transaction was already in place when this one was
> added. It nested without incident, which is what the fix sketch predicted, but the ordering
> advice was sound and was simply overtaken.

Original entry:

**Severity: high.** If the second write fails, the value silently changes with no record that
it ever did — the one thing this whole feature exists to prevent.

`restore()` calls `$entity->save()` (the value goes back) and then `$recorder->record()` (the
`origin: revert` row is written) as two separate statements, outside any transaction. A
failure between them leaves the column changed and history unaware of it. `CLAUDE.md`
requires a transaction for multi-step writes.

It also breaks the promise task 16 was extracted to keep: task 17's `revertSave()` is
specified to wrap its work in `DB::transaction`, so the single-field and whole-save paths
would fail *differently* — exactly the drift the extraction was supposed to make impossible.

**Where:** `App\Services\RevisionReverter::restore()`.

**Fix sketch:** wrap `restore()`'s body in `DB::transaction`. Task 17's outer transaction
then nests harmlessly (Laravel counts transaction depth and only the outermost commits), so
the whole-save path still gets all-or-nothing across every field.

> [!IMPORTANT]
> Fix this **before** task 17. That task builds directly on `restore()`, and adding the
> transaction afterwards means re-reasoning about a nested one.

### 3. The changelog entry describes a page that has no Revert button

**Severity: low (wrong documentation, working code). Mostly overtaken by task 17.** The
`2026-07-25 — Revert conflicts come back to the page you were reading` entry says the writer
returns to "the history you were reading". When it was written, the **only** Revert button in
the app was on the *compare* page (`resources/views/revisions/compare.blade.php:93`).

Task 17 put *Undo this save* on the history page, and its conflicts do land back there, so
the sentence is now true of the undo. It is still inaccurate about the **per-field** revert,
which remains compare-only.

**Fix sketch:** reword to "the page you were on" when the entry is next touched. Not worth a
commit of its own.

### 4. Nothing checks that the alert is ever visible — ✅ FIXED 2026-07-26

**Severity was: medium.** Closed alongside #1 and #2, since it is the hole that let #1 hide.
`RevertRevisionTest::test_the_conflict_alert_is_actually_rendered_on_the_page_it_returns_to`
performs a conflicting revert, follows the redirect, and asserts the message text appears in
the HTML; the #1 test does the same for the validation alert. The **validation** alert was
also eyeballed in a browser. The **conflict** alert still has not been: producing it needs a
stale base hash, which no amount of clicking will do — only the test reaches it.

Original entry:

**Severity: medium (a hole in the safety net, not a live bug).** `RevertRevisionTest` asserts
that the app *decided* to flash a message (`assertSessionHas('error')`). No test asserts that
any page **renders** it. The alert markup was added to
`resources/views/components/revisions-layout.blade.php` and never seen — not by a test, and
not in a browser.

If the flash were wired into the wrong place, or the component's props were wrong, the whole
suite would still pass and the feature would be exactly as broken as it was before task 16.

**Fix sketch:** one feature test that performs a conflicting revert, follows the redirect, and
asserts the message text appears in the HTML. `/run-imagoldfish` for a one-time eyeball.

### 5. The conflict check can still be raced

**Severity: very low in practice, but the docs overclaim.** The base hash is compared, and
*then* the value is written, as two separate steps with no lock and no condition on the
update. Two reverts landing at the same instant can both pass the check, and the second wins.

This app has one user, so this is close to theoretical. It is listed because
`documentation/architecture.md` presents the base-hash check as *the* answer to concurrency,
and it is not quite that — it is the answer to a **stale page**, which is the case that
actually happens (a second tab, or an autosave still in flight).

**Fix sketch:** either narrow the documentation's claim, or make the update conditional on the
value still hashing as expected.

### 6. The conflict message is vague, and its advice is stale

**Severity: low.** Two small things in the same sentence — *"This changed somewhere else since
you opened this page — reload and try again."*

* The compare page shows several fields at once, each with its own Revert button. The message
  never says **which field** moved.
* "Reload and try again" describes a step the app already took: the redirect re-rendered the
  page, so the base hashes on it are already fresh. The writer can simply click again.

**Where:** `App\Exceptions\RevisionConflictException::valueChangedElsewhere()`.

### 7. `session('error')` is a generic key on a shared shell

**Severity: negligible today.** `x-revisions-layout` renders whatever is in `session('error')`.
`RevisionController::revert()` is currently the only writer of that key in the whole app, so
nothing else can appear there. But any future feature that flashes `error` and redirects to a
revisions page would have its message shown in a revisions-flavoured alert.

**Fix sketch:** only worth acting on if a second writer of the key appears.

---

## Accepted costs

Moved here from `resolution-log.md` → *Known gaps*, which is where they were first recorded.
They are consequences of decisions taken deliberately, not work items.

### The source diff has two accessibility channels, not three

`expanded/ui.md` requires tint + glyph + a visually-hidden label on every marked passage.
`jfcherng` writes its own `<ins>`/`<del>` and offers no hook for a label, so the
Markdown/plain side gets the tint and the glyph plus the semantic elements. Closing it means
the source path emitting its own markers rather than delegating to the library — a
task-7-sized change, not a styling one. Documented in `documentation/architecture.md`.

### A coalescing autosave narrows what *Undo this save* covers

Accepted when Q1 was settled (`expanded/open-questions.md` Q1): a coalescing row keeps its
*original* `save_id`, exactly as it already keeps its original `created_at`. So a save
touching three fields where one coalesces into a still-open burst from earlier puts that
field in the **earlier** save point — the group the writer thinks of as "the save I just
made" lists two fields, and task 17's *Undo this save* then silently leaves the third alone.
The alternative (rewriting an existing group's membership after the fact) is worse. Also
documented in `RevisionRecorder::record()`'s docblock and in `documentation/architecture.md`.

### A prune leaves stale summaries behind

Accepted when Q7 was settled: deleting an `automatic` row leaves its successor's stored
`summary_html` / `change_count` describing a diff against a row that no longer exists, so the
**history list** can under-report what a save changed. The compare screen always computes
live and is unaffected. Recomputing during a mass prune would turn a cheap `DELETE` into an
O(n) diff job, which is why it was not taken. Documented in `expanded/data-model.md`'s
warning callout.
