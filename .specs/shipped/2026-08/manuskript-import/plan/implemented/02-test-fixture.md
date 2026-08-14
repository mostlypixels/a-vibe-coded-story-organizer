# 02 — Test fixture tree

**Depends on:** nothing (can run before or after 01; later tasks need it).

## Scope

* Create `tests/Fixtures/manuskript/` — the directory does not exist yet — holding one small,
  hand-written Manuskript tree, checked in.
* Contents per `../expanded/testing.md` → *Fixture*. Beyond that list, the tree must also carry:
  * an empty chapter directory (`folder.txt` only) and a scene with an empty body — both are
    imported and counted;
  * one scene body containing raw disallowed HTML, for task 04's strip-and-mark path;
  * one chapter directory whose `folder.txt` has no `title:`, for the filename fallback.
* No test code — tasks 03–05 write the tests that read this tree.

## Key decisions

* Real files on disk, not a tree generated in `setUp()`: the fixture is meant to be readable in a
  diff and matched against the real Manuskript output.
* Keep it small — two chapters, three scenes, two characters. It is a format sample, not a novel.
* Prefixes `00-` and `10-` deliberately, so a string sort and a numeric sort disagree.
* Watch `.gitignore` and `.gitattributes`: the CRLF fixture file must survive checkout unconverted,
  or task 04's line-ending test passes vacuously.
