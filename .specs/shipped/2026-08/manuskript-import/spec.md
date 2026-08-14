---
status: shipped
shipped: 2026-08-14
planned: 2026-08-13
expanded: 2026-08-13
---

# Manuskript Import

Novels drafted in [Manuskript](https://www.theologeek.ch/manuskript/) have no way into this app. The
projects to migrate use a small subset of Manuskript's features, so a faithful importer is neither
possible nor wanted — this is a one-way, best-effort migration tool.

> [!WARNING]
> **Branch-only.** This feature lives on `manuskript-import` and must never merge to `master`.
> It is shaped around the specific projects being migrated, not around Manuskript in general.

## Goals

* An artisan command: source directory + owning user in, one new `Project` out.
* Read the **exploded** on-disk format (a `MANUSKRIPT` marker file next to `outline/`,
  `characters/`, `infos.txt`), not the zipped `.msk`.
* Import, from a source tree like `D:\path\to\a-manuskript-project`:
  * `infos.txt` → project title (+ author).
  * `outline/NN-<name>/` folders → chapters, `NN` giving `position`.
  * `outline/NN-<name>/M-<name>.md` files → scenes, `M` giving `position`, the header's
    `title:` → scene `name`, the body below the blank line → `contents`.
  * `characters/<id>-<name>.txt` → codex entries of type `character`: `Name` → the entry
    name, every other field → a heading (the key) plus a block holding the value, all
    concatenated into `description`. Fields holding `?` (Manuskript's "not filled in"
    placeholder) or nothing are skipped.
* Convert scene bodies from Markdown to the HTML the editor stores (`league/commonmark`,
  already a dependency), then through the existing `Import\ContentSanitizer`.
* Idempotence is not required, but a failed run must leave no half-project: import inside a
  transaction, or delete the project on failure.
* Report a summary (counts imported, files skipped and why).

## Non-goals

* No UI, no upload, no queue, no resumable checkpointing — unlike the existing
  `ProjectImporter`, this is a local developer command. It should reuse that code where it
  fits and skip the rest.
* No round-trip: nothing exports back to Manuskript.
* No support for Manuskript features these projects do not use — anything unrecognised is
  skipped and counted, never guessed at.
* No revisions history: the import produces one baseline per record, like any other creation.
* Nothing outside scenes and characters: `world.opml`, `plots.xml`, `labels.txt`,
  `status.txt`, `settings.txt` and the scene metadata beyond the title (`POV`, `summaryFull`,
  `notes`, `compile`, the numeric cross-file IDs) are all read past, never imported.
* Files directly under `outline/` (loose notes, TODO lists) are ignored — only chapter
  subfolders are walked.
* No mapping onto the codex attribute system (`CodexAttribute` / `CodexAttributeValue`):
  character fields land in the description as text, deliberately.

## Rough approach

* One command in `app/Console/Commands`, delegating to a service under `app/Services/Import`
  (the `ProjectGraphImporter` split — one method per entity level — is the pattern to follow).
* A small parser for the shared file format (padded `key:` header, blank line, body) used by
  `folder.txt`, scene `.md`, and character `.txt` alike; multi-line values are continuation
  lines indented to the value column.
* Hierarchy mismatch: Manuskript has chapters → scenes, this app has acts → chapters → scenes.
  Create one synthetic act to hold every chapter.
* Fixtures: a trimmed copy of a real project under `tests/Fixtures`, feature-tested end to end.

## Open questions

* Synthetic act naming, and whether a `--act` option is worth it.
* Scenes all import at the default `SceneStatus` — Manuskript's status vocabulary is
  user-defined (`status.txt`, French here) and does not map onto the enum.
