# 06 — Real-tree smoke run

**Depends on:** 05.

## Scope

* Run the finished command against the author's real source tree (a local `D:\` Manuskript
  directory, never added to the repo), into the dev database.
* Verify the result in the running app with the `run-imagoldfish` skill: the story overview (21
  chapters in source order, scenes under each), one long scene's prose, and two character entries
  (a richly filled one and an all-`?` one).
* Fix whatever the real tree exposes that the fixture did not, and record it in
  `../resolution-log.md`. If a fix changes behaviour, it needs a fixture case and a test.
* No new feature work: anything the run reveals as genuinely out of scope goes in the resolution
  log, not into the code.

## Key decisions

* Dev-DB side effects are fine — reseed with `migrate:fresh --seed` afterwards if the imported
  project is in the way.
* Expected shape from the source: 21 chapter directories, 51 character files, 2 loose files under
  `outline/` (`21-New.md`, `22-TODO.md`) reported as skipped.
* The real tree is never added to the repo and never referenced by a test.

## Tests

None new unless the run exposes a defect — then a fixture case plus its assertion, in the file the
relevant task already created.
