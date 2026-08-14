# Architecture

No migrations, no new models, no routes. Three new classes plus a test fixture.

| File | Role |
|---|---|
| `app/Console/Commands/ManuskriptImportCommand.php` | Argument parsing, user resolution, the printed report. No domain logic. |
| `app/Services/Import/ManuskriptImporter.php` | Reads the tree, writes the graph, returns a result object with the counts and skips. |
| `app/Services/Import/ManuskriptFile.php` | Parses the one file format Manuskript uses (padded `key:` header, blank line, body). |

Precedent to follow: `App\Services\Import\ProjectGraphImporter` (one private method per graph
level, the service HTTP-agnostic and the caller thin) and `WordCountDemoHistoryCommand` (an
authoring-only command that validates its input and returns `SUCCESS`/`FAILURE`).

## Command signature

```
manuskript:import
    {path       : Path to the exploded project directory (the one holding the MANUSKRIPT file)}
    {--user=    : Owner of the imported project — a user ID or email}
    {--name=    : Override the project name (default: infos.txt's Title)}
    {--act=     : Name of the single act holding every chapter (default: "Act 1")}
```

`--user` is required unless the install has exactly one user, in which case it defaults to that
user. Both misses are a `FAILURE` with a clear message, never a prompt.

## The file format

Every `folder.txt`, scene `.md` and character `.txt` shares one shape:

```
Key:            value
                continuation line
Another key:    value
<blank line>
body…
```

* A header line is `^([^:]+):[ \t]*(.*)$`; anything else before the first blank line is a
  continuation appended to the previous key's value (`\n`-joined, the padding stripped).
* The header ends at the first blank line. Everything after it is the body, verbatim.
* Keys are matched **case-insensitively and trimmed** — scene headers use `title:`, character files
  use `Name:`.
* A character file has a header and no body; a scene `.md` has both; `folder.txt` has a header only.

`ManuskriptFile` returns `(array $header, string $body)` and knows nothing about what the keys mean.

## Reading the tree

1. **Gate.** `path/MANUSKRIPT` and `path/outline/` must exist, or fail before writing anything.
2. **Project.** `infos.txt` → `Title` → `name` (or `--name`), `Author` → `author`. No description.
3. **Act.** One act, `--act` or `"Act 1"`, position 1.
4. **Chapters.** Directories directly under `outline/`, sorted by their numeric `NN-` prefix
   (numeric, not string: `10-` must follow `9-`). Name from `folder.txt`'s `title:`, falling back to
   the directory name with the prefix stripped and `_`/`-` turned back into spaces.
5. **Scenes.** `*.md` directly inside a chapter directory, same numeric sort. Name from `title:`
   with the same filename fallback; `contents` is the body.
6. **Characters.** `characters/*.txt`, sorted numerically. `Name` → `CodexEntry.name`
   (type `character`); every other key → the description, in file order.
7. **Skipped, counted, reported, never guessed at:** files loose under `outline/`, non-`.md` files
   inside a chapter directory, empty chapter directories, character files with no `Name`, and every
   other top-level file (`world.opml`, `plots.xml`, `labels.txt`, `status.txt`, `settings.txt`).

Positions are the **1-based index in the sorted list**, not the source prefix — source prefixes have
gaps and duplicates, and `position` is contiguous everywhere else in the app.

## Character description HTML

Per filled field, in file order:

```html
<h3>Last name</h3><div>Murray-Parker</div>
```

* The value is HTML-escaped (`e()`), then newlines inside a multi-line value (a character `Notes:`
  block) become `<br>`. `h3`, `div` and `br` are all in `RichTextFields::ALLOWED_TAGS`, so the
  `SanitizesRichHtml` mutator on `CodexEntry.description` passes the result through unchanged.
* Skipped: `Name` (it is the entry name), and any field whose trimmed value is `''` or `?` —
  Manuskript's "not filled in" placeholder, which is most of them.
* `Color`, `ID`, `Importance`, `POV` are ordinary fields here: they get a heading like any other.
  Not worth a special case on a branch-only importer.

## Pitfalls

* **Line endings.** `NormalizeLineEndings` is HTTP middleware; a console import never passes through
  it. Normalize CRLF → LF in `ManuskriptFile` on read, or every imported scene diffs dirty against
  its first edit.
* **Scene `saved` hooks.** Each scene write fires `WordCountSnapshotRecorder` (`Scene::booted()`),
  which sums the whole project's scenes. Acceptable at this size; do not "optimise" it away, and do
  not bulk-`insert()` past it — `word_count` is set in the same `saving` hook.
* **One transaction.** Wrap the whole import in `DB::transaction()`. Unlike `ProjectImporter` there
  is nothing to resume: a local directory can simply be re-imported.
* **Project creation side effects.** `Project::booted()` creates the main plotline and the
  Start/End fixed events. The importer must not create them, and must not touch the main plotline.
* **Markdown gate.** Run each scene body through `ContentSanitizer::assertMarkdownAllowed()` and
  fail the import naming the offending file. The content is the user's own, but the check is free
  and the app's Markdown renderer passes raw HTML straight through.
* **UTF-8.** Source filenames and headers carry accents; read with no encoding conversion and never
  `basename()` for display without it.
