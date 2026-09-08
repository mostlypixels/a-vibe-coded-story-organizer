---
title: "Task 01 — Sheet predicates"
---

# Task 01 — Sheet predicates

## Scope

`app/Services/CodexAttributeSheets.php` only: the two predicates and the picker's option
list. This closes the drift between `setOnly()` and `CodexAsOfResolver`.

Does **not** change any controller, Blade, or route — task 05 moves `edit()` onto
`attached()`. `show()` keeps calling `setOnly()`; only what it returns changes.

## Depends on

Nothing.

## Key decisions already made

* `attached(CodexEntry, Event $startEvent): Collection` — today's `setOnly()` body (reject a
  sheet with no baseline and no periods), renamed to what it really tests.
* `setOnly(CodexEntry, Event $startEvent): Collection` — reject unless the baseline or one
  period passes `filled($value->value)`. This is the defect fix.
* `unattachedFor(CodexEntry): Collection` — `project->codexAttributesFor($entry->type)`
  minus attached ids, in `position` order.
* `forEntry()` becomes **private**. `attached()` and `setOnly()` build on it internally.
* Callers must eager-load `attributeValues.startEvent` first, as both controllers already do
  — `AttributeTimeline::orderedValues()` has a fast path for it.

Detail: `expanded/architecture.md` → *`App\Services\CodexAttributeSheets`*.

## Tests to add

Extend `tests/Feature/CodexAttributeSheetsTest.php`. The existing `forEntry()` test moves
onto whichever public method now covers it.

* `attached()` keeps a pair whose only row is `''`; drops a pair with no rows.
* `setOnly()` drops a pair whose only row is `''` — **fails before this change**.
* `setOnly()` keeps a pair whose Start is `''` but a later period is filled.
* `unattachedFor()` excludes attached ids, and excludes attributes of another entry type.
* `CodexAsOfResolver` output is unchanged for an all-blank pair — the two now agree.
