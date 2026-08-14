# 03 — Command, gate, project and act

**Depends on:** 01, 02.

## Scope

* `app/Console/Commands/ManuskriptImportCommand.php` — signature exactly as
  `../expanded/architecture.md` → *Command signature*; argument/option handling, user resolution,
  the printed report, `SUCCESS`/`FAILURE`. No domain logic.
* `app/Services/Import/ManuskriptImporter.php` — the gate (`MANUSKRIPT` marker + `outline/` must
  exist), `infos.txt` → project name/author, the single act, the surrounding `DB::transaction()`,
  and a result object carrying counts + skipped-file reasons.
* Chapters, scenes and characters are **not** imported here (tasks 04 and 05). The importer walks no
  further than the project + act; the report structure it returns must already have the slots those
  tasks fill.

## Key decisions

* `--user` accepts an ID or an email; it may be omitted only when the install has exactly one user.
  Absent/ambiguous/unknown user is a `FAILURE` with a clear message and no prompting.
* `--name` overrides `infos.txt`'s `Title`; `--act` overrides `"Act 1"`.
* The project's `description` is left null — Manuskript's `summary.txt` is out of scope.
* Never create the main plotline or the Start/End events; `Project::booted()` owns them.

## Tests

`tests/Feature/ManuskriptImportCommandTest.php`, driven through `$this->artisan(...)`: happy path
(project name, author, one act named "Act 1"); `--name`/`--act` overrides; `--user` by ID and by
email; missing `MANUSKRIPT` marker and nonexistent path both `FAILURE` with `Project::count()`
unchanged; ambiguous user with several users in the DB is a `FAILURE`.
