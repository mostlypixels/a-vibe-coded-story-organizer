# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), adapted
so the heading answers *when something shipped*: each merged pull request (or
directly-landed feature) adds its own dated `## YYYY-MM-DD — <title> (#PR)` section at
the top, below `[Unreleased]`, grouping its entries by change type (`Added` / `Changed`
/ `Fixed` / `Removed`). `[Unreleased]` holds only work that has not merged to `master`
yet — when the PR carrying an entry merges, the entry ships under its dated heading.
The per-commit "why" lives in each commit message body; richer rationale for a change
set belongs in its pull request description.

A section with no `(#PR)` suffix landed directly on `master`, before the protected-branch
workflow existed — it is not a missing number. Everything from `2026-07-17` onward went
through a PR, and `scripts/pr-land.sh` stamps the number automatically.

## [Unreleased]

## 2026-09-02 — JavaScript tests in the automated checks

### Added

- The automated checks now run the JavaScript test suite. They ran only the PHP
  suite and the formatter, so a broken JavaScript test could reach `master`.

### Fixed

- JavaScript tests no longer fail on Node 24 and later. Node defines its own
  `localStorage`, which hid the one jsdom installs and broke 15 tests.

### Changed

- The automated checks use Node 24 instead of Node 20, to match the development
  machines.

## 2026-08-24 — Inline event creation fix (#132)

### Fixed

- Typing a new event inline on a codex Born/Died field or a scene's "happens during" now saves it, instead of dropping it without warning.

## 2026-08-23 — Birth and death (#131)

### Added

- Codex entries can record when they start and end, linked to timeline events, labelled by type (born/died, created/destroyed, founded/dissolved).
- A scene's codex panel shows each entity's age, and hides entities not yet born or already gone at that moment.

## 2026-08-23 — Codex menu separator (#130)

### Changed

- The Codex menu now sets the entry types apart from the Attributes and Tags pages with a divider.

## 2026-08-23 — Tag management page (#129)

### Added

- A Tags page under Codex lists every tag in the project with how many entries use it, and adds, renames, or deletes tags.

## 2026-08-23 — Codex tag suggestions no longer clipped (#128)

### Fixed

- The tag suggestion list on a codex entry no longer gets cut off by the card edge.

## 2026-08-23 — Onboarding two-column layout (#127)

### Changed

- Onboarding lays out in two columns on wide screens so the guidance and the project form sit side by side.
- Blank is no longer a genre tile; start a blank project with the "Skip and start blank" link.

## 2026-08-22 — Genre-based onboarding (#126)

### Added

- Onboarding now offers a genre; picking one seeds the first project with fitting codex attributes, tags, example entries, and a starter book.
- New writers can install a demo project from onboarding to explore a finished story.

### Changed

- Demo projects no longer seed automatically on a fresh database.

## 2026-08-21 — Onboarding and clearer project navigation (#125)

### Added

- A new account with no projects lands on an onboarding page that prompts for the first project.

### Changed

- The site logo now opens the active project's dashboard, or the project list when none is open.
- The project list moved from `/dashboard` to `/projects`.
- The nav book picker now opens the project dashboard instead of the book home page.
  Picking a book records it as the project's last book on the way through.
- The Melusine demo seeders name their book ("First book", "Premier livre", "Primo
  libro"), so a seeded project shows the book layer in the interface.
- The "Recent scenes" breadcrumb now numbers the location, chevron-separated like
  the page breadcrumb ("Act 1 › Chapter 1: <name>"). The project dashboard prefixes
  "Book N", so a writer sees which book a scene sits in without its full name.

## 2026-08-20 — Docker as the default way to run the app (#124)

### Changed

- Docker is now the documented way to run the development app; the native server is the alternative.

### Fixed

- `make up` and the native start script each refuse while the other holds port 8000.
- `make up` and `make rebuild` no longer fail to parse when Make runs from Git Bash.

## 2026-08-20 — Word count challenges (#123)

### Added

- Set a word target for a date range, or for every month, on the Progress page.
- A challenge shows words so far, par for today, ahead or behind, and words a day still needed.
- A finished challenge stays on the page as a record, marked met or missed.
- Challenges travel in the project export archive.

### Fixed

- A negative word count now reads "words" instead of "word".

## 2026-08-19 — Smart punctuation while typing (#122)

### Added

- The editor converts punctuation as you type: `--` becomes an en dash, `---` an em dash,
  `...` an ellipsis, and quotes and apostrophes curl. Hyphenated words are left alone.
- This matches what scene text already became in an exported EPUB, so the editor now shows
  what the book will show.

### Notes

- Conversion happens on typing only. Text that arrived by import keeps whatever it had.

## 2026-08-19 — The compare screen sees more formatting (#121)

### Fixed

- Ticking or unticking a task-list item is reported as a change. It previously saved a
  revision that the compare screen showed as unchanged.
- Adding subscript or superscript is reported as formatting. It previously read as the
  text having been deleted and retyped.
- The same applies to any formatting that starts in the middle of a word, such as bolding
  part of it.

## 2026-08-19 — Block alignment and named text colour for HTML fields (#120)

### Added

- Rich HTML fields (descriptions, scene notes, codex) support block alignment and five
  named text colours, styled in the app and in exported EPUBs.
- The revision compare screen reports an alignment or colour change as formatting, not as
  deleted and re-typed text.
- Scene contents stay structural: neither control appears on a Markdown field, and the
  classes are stripped if they reach one by another route.

## 2026-08-19 — Sanitize rendered scene Markdown

### Fixed

- Scene Markdown is sanitized when it is rendered. Markdown carries raw HTML through and
  `ValidMarkdown` rejects none of it, so anything typed into `Scene.contents` reached the
  page unfiltered — including on the unauthenticated scene share link and in the exported
  static site and EPUB. Event-handler attributes, `javascript:` links, `<iframe>` and
  inline `style` are now removed. The `<u>`, `<sub>` and `<sup>` tags the editor writes
  itself are kept.

### Changed

- `App\Support\AuthorMarkdown::render()` sanitizes; `renderUnsanitized()` is the
  deliberately unsafe variant the import allow-list check and the word counter need.

## 2026-08-19 — Markdown strikethrough renders as `<s>`

### Fixed

- Markdown strikethrough (`~~text~~`) renders as `<s>` instead of `<del>`. `<del>` is
  reserved for generated revision diffs and is not in the rich-text allow-list, so the
  sanitizer stripped it: on import, every paragraph holding a strikethrough failed the
  allow-list check and was replaced with `[INVALID CONTENT REMOVED]`, and on screen the
  strike simply vanished. `<s>` is what the WYSIWYG editor already writes.

### Added

- `App\Support\AuthorMarkdown` is the one renderer for author-written Markdown, so the
  scene view, revision view, word counter, and import check cannot drift apart.

## 2026-08-19 — Comment and documentation cleanup (#115)

### Changed

- Documentation is reorganised around shorter, task-focused guides.

### Removed

- Comments that repeated what the code already says.

## 2026-08-18 — Multiple books per project (#114)

### Added

- A project can hold several books, each with its own acts, chapters and scenes.
- An act can move to another book of the same project, keeping its chapters and scenes.

### Changed

- Act, chapter and scene numbering restarts in each book.
- Ebook export produces one file per book, with its own cover, publication settings and appendix.
- Rights, dedication, acknowledgements, preface and postface belong to the book, not the project.
- Archives exported before this change can no longer be imported; only manifest version 4 is accepted.

## 2026-08-16 — No revisions in import/export (#112)

### Removed

- Revision history no longer ships in project export archives, and the export form's toggle for it.

### Changed

- Archives exported before this change can no longer be imported; only manifest version 3 is accepted.
- The revision storage panel no longer offers an "Imported" category to bulk-delete.

## 2026-08-16 — Branded error pages (#111)

### Added

- Branded 403, 404, and 500 pages that follow the active theme and font.
- A reduced navigation bar on the error pages, with the project picker and Configuration.

### Fixed

- An unknown URL now knows who is signed in, so its page carries the navigation bar.

## 2026-08-16 — A second seeded user with a minimal project (#110)

### Added

- The seeder creates a second user owning a small "Lorem ipsum" project, so the demo data has more than one owner.

## 2026-08-15 — Total words in a footer on the Acts, Chapters, and Scenes lists (#109)

### Added

- A totals footer on the Acts, Chapters, and Scenes lists, summing words (and chapters or scenes) across the rows shown.

## 2026-08-15 — Cap search columns and page each domain's full results (#108)

### Added

- Each search column shows its top matches with a "See all N results" link to a dedicated, paginated page for that entity type.
- A footer band on data tables for a per-table action, such as the search "See all" link.

### Changed

- Search results are capped per column so a broad term no longer renders hundreds of rows on one page.

## 2026-08-15 — Page the Story overview one chapter at a time (#107)

### Added

- A chapter pager and whole-book table of contents on the Story overview.
- An owner-only setting to switch the Story overview between one chapter per page and the entire book.

### Changed

- The Story overview shows one chapter per page by default, keeping long stories responsive.

## 2026-08-13 — Keep the text a field started from (#105)

### Fixed

- The oldest revision of a field held the text of the first edit instead of the text that came before it.

## 2026-08-13 — Match line endings between form saves and autosaves (#104)

### Fixed

- Comparing a form save with an autosave of the same text marked every line as changed.

## 2026-08-12 — Preview a colour theme before saving it (#103)

### Changed

- Picking a colour theme on Appearance & accessibility repaints the page at once, as the font choices already did.

## 2026-08-12 — Save a codex entry without deleting it (#102)

### Fixed

- Saving a codex entry from its edit page deleted it instead.
- Saving a codex entry no longer drops the cover, tags and reference media it was submitted with.

### Changed

- Every create page carries the same sidebar Actions card as its edit page, with Create and Cancel.
- The plotline edit page gains Save and stay, History, and a Delete button, and hides Delete on the main plotline.
- The project list offers Edit and Delete on each project, in both the list and grid views.

## 2026-08-12 — A "No halation" colour theme (#101)

### Added

- A fourth colour theme, "No halation": Low-glare dark's surfaces with far softer text, buttons and status colours, for readers that bright colour blooms for. It sits below the WCAG contrast minimums on purpose.

## 2026-08-12 — Theme picker as flags (#100)

### Changed

- Configuration → Appearance is now titled after itself, and the theme card is called "Colour theme".
- Themes are picked from a grid of rectangular flags, five per row like the font picker, instead of a list of colour dots. Each flag paints the theme's own colours behind its name.

## 2026-08-11 — Per-user fonts and text sizing (#99)

### Added

- Configuration → Appearance now has a font picker: pick a typeface for the interface and another for your manuscript, from ten families shown in their own face. Families designed for impaired reading are marked with an eye icon.
- Text size and line spacing are set separately for the interface and the manuscript, on five-step sliders. Line spacing is a multiplier of the normal spacing, so 2× is double-spaced.
- Everything previews live as you pick, before you save.

### Changed

- The default interface font is now Inter instead of Atkinson Hyperlegible. Everyone who never picked a font sees the change; pick Atkinson under Configuration → Appearance to keep it.

## 2026-08-10 — Dark theme by default, and web fonts that load (#98)

### Changed

- The app opens in Low-glare dark. Pick another theme under Configuration → Appearance to override it.

### Fixed

- Web fonts now load. Every page quietly fell back to a system font instead.

## 2026-08-10 — Duplicate scenes and codex entries (#97)

### Added

- A scene can be duplicated from its list row or its edit page. The copy lands right after the original.
- A codex entry can be duplicated the same way, carrying its aliases, images, attachments, timeline values and tags.
- Duplicating asks for a name first, prefilled with the original plus a number that steps up until it is free.

### Fixed

- Dialogs no longer open underneath their own grey backdrop.
- The confirm button of a dialog now works. Deleting an act or a chapter that holds children was unreachable this way.

## 2026-08-10 — Temporary export cleanup (#94)

### Fixed

- An export download that never finishes no longer leaves its temporary file on the server.

## 2026-08-09 — Word count goals and progress (#91)

### Added

- A project takes a daily and a total word goal, shown as progress bars on its dashboard.
- A Progress page charts the words written per day over a range you choose.
- The dashboard counts the consecutive days you met your daily goal.
- Your profile takes a timezone, which decides when a writing day starts and ends.
- Exports and imports carry the word count history with the project.

### Changed

- The dashboard opens with your recent scenes and codex entries, instead of one tile per entity kind.

### Removed

- The project description no longer repeats on the dashboard.

## 2026-08-08 — Pick up where you left off (#89)

### Added

- The Story, Timeline and Codex landing pages list what you edited most recently, with a link to the full index.
- The project dashboard shows the latest act, chapter, scene, plotline, event and codex entry of each type.

### Removed

- The dashboard's plotline and event count tiles, replaced by the latest-edited tiles above.

## 2026-08-07 — Breadcrumb trails name the page they label (#88)

### Fixed

- A breadcrumb that is both a link and the current page is announced as the current page.

### Changed

- A Tools page with no breadcrumb trail of its own shows no trail, instead of one reading "Revisions".

## 2026-08-07 — Scene reordering, development-server fonts, and a faster watcher (#87)

### Fixed

- Moving a scene up or down on the Story overview reorders the page again, instead of only saving.
- Web fonts load in the development server, so pages no longer render there in a fallback family.

### Changed

- The development server starts faster and stops reloading the page on unrelated file changes.
- The stylesheet no longer ships utility classes that only the agent prompts mention.

## 2026-08-07 — Editor edits that are not typing now autosave (#86)

### Fixed

- Deleting all the text in a rich-text field is saved automatically; the old text no longer stays stored.
- Toolbar formatting, Delete, Backspace and undo in an editor now autosave, as typing already did.

## 2026-08-07 — Dependency updates, closing the remaining security advisories (#85)

### Changed

- Updated the PHP and JavaScript dependencies, closing every open security advisory.
- Held the test runner at its current minor version: the newest one does not work with the parallel test tool.

## 2026-08-07 — Markdown parser security update (#84)

### Changed

- Updated the Markdown parser, closing a flaw where crafted Markdown could hang the server.

## 2026-08-07 — Autosave no longer mirrors drafts to localStorage (#83)

### Removed

- The crash-recovery prompt offering to restore an unsaved draft after a reload.
- Unsaved text is no longer kept in the browser's local storage; the warning before leaving a page with unsaved changes is unchanged.

## 2026-08-06 — Code comments no longer point at files that get deleted (#82)

### Added

- A code-comment rule that loads when a PHP file is edited, so it reaches the author of the comment.

### Changed

- Comments now state a rule instead of citing where it was decided, and avoid facts that go stale in silence.

### Removed

- Every comment reference to a handoff or plan file, both of which are deleted or moved once a feature ships.

### Fixed

- Comments that had drifted out of date, including a wrong count and a mention of a build step that no longer exists.

## 2026-08-05 — Agent instructions trimmed and split into path-scoped rules (#81)

### Changed

- Documentation, verbosity and changelog writing rules moved from `CLAUDE.md` into `.claude/rules/`, so they load only when the paths they govern are touched.

### Removed

- Generic coding-principle, security, naming and code-style bullets that no longer earned their place in `CLAUDE.md`.

## 2026-08-05 — Breadcrumb trails and section landing pages (#80)

### Added

- Story, Timeline, Codex, and Tools each have a landing page, reachable from its menu and breadcrumb.

### Changed

- Project pages now show a breadcrumb trail instead of a page title and "Back to X" link.
- The "Story Overview" menu item is now "Overview", and the page moved to a new address.

## 2026-08-05 — Theme quick fixes: collapsible cards, Tabler icons, toolbar dropdowns (#78)

### Added

- Card sections on entity pages can now be collapsed and expanded.
- The rich-text slash menu now offers subscript and superscript.

### Changed

- The rich-text toolbar groups less-common formatting into labeled dropdowns (Style, Typography, Lists, Callout, Code, Table).
- The callout button is now a dropdown of the five types; picking one inserts a callout or changes the current one's type, instead of cycling on repeated clicks.
- UI icons across the app now come from a single Tabler icon set for visual consistency.

## 2026-08-03 — Continuous chapter and act numbering (#77)

### Added

- The scenes list shows each scene's position within its chapter, next to the chapter column.
- The website book export now shows chapter and act numbers, matching the ebook export.

### Changed

- Act, chapter and scene numbers now count continuously across the whole project and never
  show a gap, instead of resetting at each act or chapter.
- The ebook export no longer skips chapters or acts with no scenes — it now includes them as
  placeholder pages.

### Fixed

- Sorting the chapters and scenes list by `#` now follows story order, even after an act has
  been reordered.

## 2026-08-03 — Remember the active project (#73)

### Added

- The last project you opened is remembered on your account, so it survives logging out.

### Changed

- The project navigation stays visible on the dashboard, your profile and Configuration.
- Logging in goes straight to your last project instead of the dashboard.

## 2026-08-02 — Concision rules and consistent spec-skill names (#72)

### Changed

- The spec pipeline skills are now `/mp-draft-spec`, `/mp-expand-spec` and `/mp-plan-tasks`.
- Drafts, expanded specs, plans, changelog entries and pull requests follow explicit concision rules.

## 2026-08-02 — Theme switcher (#71)

### Added

- The app can be switched between three colour themes — Daylight, Dusk, and a low-glare
  dark theme — from Admin → Appearance.
- `php artisan theme:ramp`, a developer tool for generating an accessible colour ramp from
  one anchor colour.

### Changed

- Every colour in the app now comes from a themeable token instead of a fixed hue, so a
  chosen theme repaints the whole app consistently.
- Colours were adjusted across all three themes wherever text, icons or focus outlines fell
  below the contrast minimum.
- The landing page is stripped down to the app name and a themed login button.

## 2026-08-01 — Collapse the form controls into components, drop two dead fonts (#70)

### Added

- `x-select` and `x-textarea` components, completing the set alongside `x-text-input`.

### Changed

- 37 raw `<select>`, `<textarea>` and `<input>` elements that retyped the shared border/focus/shape
  class string now use the components. `x-wysiwyg`'s no-JS fallback delegates to `x-textarea`.

### Removed

- The Figtree and Instrument Sans `@font-face` blocks and their 12 `.woff2` files. Left over from
  the Laravel starter kit; `--font-sans` names only Atkinson Hyperlegible Next, and no rendered page
  could reach either family.

## 2026-08-01 — Port the build to Tailwind 4 (#69)

### Changed

- Build moved to Tailwind 4; PostCSS and Autoprefixer removed — `@tailwindcss/vite` compiles
  the stylesheet directly inside Vite.
- The active nav link now shows a focus ring, closing a keyboard-accessibility gap the v3
  design left open.
- Browser floor: Safari 16.4+ / Chrome 111+ / Firefox 128+, documented not enforced.

## 2026-07-31 — AVCSO: a project picker, page titles, and a demo seed worth showing (#67)

### Added

- The English Melusine demo seed is a fuller book: the scene prose is expanded and
  Markdown-formatted, and the Codex gains 8 characters, 6 locations, and the default
  character-sheet attributes (Skin color, Eye color, Build, Height, Gender, Religion,
  Race, Occupation, Priorities, Secrets, Hobbies, Fears) — defined but left unvalued, the
  way a real project starts.
- `Architecture style` (Location-scoped) replaces the removed `Frescoes` attribute and
  carries the Castle of Lusignan's timeline: bare promontory → raw white marble → roofless
  ruin. It keeps a location attribute demoed end to end, so `applies_to` filtering is
  exercised from both the Location and the shared Character+Organization side.

- A project picker in the navigation bar: the left block names the open project and switches to
  another without going through the dashboard. The list is capped at five (ordered by name) with
  "All projects" as the complete list, and both the desktop and responsive menus share one query
  for it.

### Changed

- The page header is full-bleed and shallower, matching the navigation bar it sits under rather
  than the `max-w-7xl` content box below it.
- Location and Organization codex entries in the demo seed dropped their leading "The"
  (`Castle of Lusignan`, `Branded Mountain`, …). `SceneReferenceMatcher` is case-sensitive
  and whole-word, so the article kept entries from matching prose that says "the Branded
  Mountain" — the castle went from 15 scene references to 17, and the mountain from 0 to 1.
- Four locations gained a lower-case common-noun alias (`fountain`, `caves`, `cellar`,
  `the tower`), the only way the prose ever names them. Singular `cave` and bare `tower`
  are deliberately excluded: they also mean other places in the text.
- The app is named **AVCSO** (`APP_NAME`), and the browser tab now says which project you are in:
  `"<project> - AVCSO"` on authenticated pages inside a project, `"AVCSO"` everywhere else. The
  title comes from `App\Support\PageTitle`, fed by the same view composer as the navigation, so a
  shallow route like `/scenes/{scene}/edit` still resolves its project.
- A scene's event on the Story overview reads `Set during <event>` rather than
  `during <event>`.

### Fixed

- Seeding builds the `scene_codex_entry` cache it writes the data for: each Melusine seeder
  ends by calling `SceneReferenceMatcher::syncProject()`
  (`Database\Seeders\Concerns\SyncsCodexReferences`). No seeded write reaches the call sites
  that normally sync it, so every Codex sheet in the demo used to claim no scene mentions it.
- Vite in the dev container watches the bind-mounted source by polling
  (`VITE_USE_POLLING`, 60s interval), so a new Tailwind class in a template reaches the
  browser. No filesystem event crosses the Windows/macOS→container boundary, so without
  it the served CSS never regenerates — indistinguishable from a stuck browser cache.

## 2026-07-30 — Templates hold markup, not logic (#66)

### Changed

- The primary navigation's route matching moved out of the Blade template into
  `App\Support\ProjectNavigation`, supplied by a view composer. The desktop and responsive
  menus are now Blade components (`x-navigation.project-menu`,
  `x-navigation.responsive-project-menu`) reading the same view model, plus shared
  `dropdown-trigger` and `section-heading` components.
- The Configuration sidebar and the Export & import subnav now get their links from
  `App\Support\AdminNavigation` instead of inline `@php`, and all three sidebar-style link
  lists (those two plus the revisions browser's) render through one `x-sidebar-link`
  component — the active-state classes had been copy-pasted between them.
- The WYSIWYG toolbar's button definitions moved out of the Blade template into
  `App\Support\WysiwygToolbar`, and every toolbar button now renders through
  `x-wysiwyg.toolbar-button`. The Headings dropdown trigger's label comes from
  `headingLabel()` in `wysiwyg.js` instead of a nested-ternary JS expression built as a
  string in PHP.

### Fixed

- The responsive menu's Tools highlight no longer depends on `$toolsActive` leaking from the
  desktop menu's `@php` block — a latent break for any reordering of the layout.
- Toolbar buttons with no active state (the four table-structure row/column ops) rendered two
  `class` attributes, so the browser dropped the second one and they lost their sizing and
  padding.

## 2026-07-29 — See how long it is (#63)

### Added

- **A word count under every text field, updating as you type.** It appears on all fourteen
  autosaving fields — scene text above all, but also descriptions, notes and the rest — so you
  can watch a session's work add up without leaving the page. It is an estimate while you type
  and settles to the exact figure when the field saves a moment later; in between it counts a
  little generously — inside a code block, for instance, which the saved count ignores.
- **Word counts everywhere you look at your story.** Each scene, chapter and act carries its
  own, the story overview shows the whole book, and the project page puts the total in its
  header. The scene, chapter and act lists each gained a **Words** column.
- Counts follow the same rule everywhere: anything with a letter or a digit in it is a word,
  so a scene break like `* * *` counts as nothing, and `mother-in-law` counts as one.
  Formatting marks are never counted, and neither is a fenced code block.

### Fixed

- **Search results no longer run the end of one paragraph into the start of the next.** A
  heading followed by a paragraph used to appear in the snippet as `Chapter OneShe waited.`,
  which also cost you a word at every such join.

## 2026-07-27 — Say which field moved when a revert is refused (#60)

### Fixed

- **A refused revert now names the field that moved.** The compare screen shows several
  fields at once, each with its own revert button, and the alert only said "this changed
  somewhere else" — leaving you to work out which one. It also told you to reload, which
  the app had already done for you; it now says the page is up to date and you can simply
  click again.
- **Two reverts landing at the same moment can no longer overwrite each other.** The check
  that stops a revert from discarding text you never chose to lose was made one step with
  the write it guards, rather than a step before it.
- A revert's alert can no longer be impersonated by an unrelated part of the app that
  happens to report an error while you are on a history page.

## 2026-07-26 — Show both versions where "Revert to this" can point at one (#59)

### Added

- The compare page shows each changed field's **whole value on both sides**, in two columns
  under the inline diff, with **Revert to this** under the version it restores. The button used
  to sit in the card header, where it pointed at neither side of an inline diff and read as
  "revert to… what?". A side that is already the field's live value shows a "Current version"
  badge instead of a no-op button.

### Changed

- A compare card now names what it is showing — *Comparing changes to Scene field 'Contents'*
  rather than a bare *Contents* — and the diff sits in a titled *What changed* pane, the same
  shell as the two value columns, so all three labelled panes read as one comparison.

## 2026-07-26 — Write down what the review learned, where it will be read (#58)

### Changed

- `documentation/epub-export.md` now says what `validatePackage()` does **not** check: it is XML
  well-formedness plus an OPF schema, so a book with an empty navigation label validates clean
  and only looks broken in the reader (the defect fixed in #56). Also records which large
  services — `EpubExporter`, `StaticSiteExporter`, `ProjectGraphImporter`, `ArchiveValidator` —
  have never been read closely, so their size reads as unexamined rather than endorsed.
- The `ship-pr` skill warns not to edit the working tree while `scripts/pr-land.sh` runs: its
  final `git checkout master` aborts on uncommitted changes, and the script then exits non-zero
  for a PR that merged perfectly well.
- `ProjectSearch` explains why its query loads every column rather than the searchable ones —
  for a scene those are the same columns, so narrowing trades a rounding error for a runtime
  risk.

## 2026-07-26 — One name for each autosaved field (#57)

### Changed

- `config/revisions.php` keyed its per-field windows and caps by model class basename
  (`Scene.contents`) while the registry that reads them keys by URL slug (`scene`), with a
  translation step in between. The same fourteen fields had two names, and anyone adding a
  fifteenth had to get both schemes right. Config now uses the slug — `scene.contents`,
  `project.dedication` — and the translation is gone.

### Added

- `RevisionDataModelTest` walks the registry in both directions: every per-field config key must
  name a registered field, and every registered field must resolve both its window and its cap.
  A key naming no field is invisible otherwise — the lookup falls through to `default` and
  silently applies the wrong number rather than failing.

## 2026-07-26 — No blank rows in an exported book's contents (#56)

### Fixed

- A chapter whose name is blank, exported under the "Title only" chapter format, produced an
  empty label in the EPUB's navigation and table of contents — a blank row in the reader's
  contents list. The export's own validity check inspects the package metadata, not the
  navigation, so the book exported clean and only looked broken once opened. Navigation labels
  now fall back to "Chapter {position}", matching what a nameless act or scene already does.
  The chapter's own page heading is untouched: a writer who asks for the title alone, and has
  none, still gets none.

## 2026-07-26 — Don't say "saved" while the last words are still unsaved (#55)

### Fixed

- Typing while an autosave was in flight left the page believing everything was saved. The
  response cleared the field's unsaved-changes flag unconditionally, including when it described
  text the field no longer held — so for the couple of seconds until the pending save fired, the
  "you have unsaved changes" prompt would let a tab close or a link navigate away in silence.
  That is the exact window the warning exists to cover. The flag is now cleared only when the
  text that was sent still matches what is in the field. No text was ever lost; the warning was.

## 2026-07-26 — Keep the newest revision, whichever order it was written in (#54)

### Fixed

- The scheduled prune could delete a field's **newest** revision. It protected "the newest row
  per field" by highest database id, while every other query in the feature — the history list,
  the snapshot, the reverter — decides newest by `(created_at, id)`. Those two agree only while
  rows are inserted in timestamp order, and baselines are deliberately back-dated to the entity's
  `updated_at`. Where they disagreed, the prune kept the *older* row and deleted the version the
  writer would have been shown. It now deletes a row only when a strictly newer sibling exists,
  by the same ordering as the rest of the feature.

### Added

- A prune test whose two revisions have timestamp order and insertion order deliberately
  reversed. The previous query fails it.

## 2026-07-26 — Two fewer queries on every autosave (#53)

### Changed

- Every autosave re-`SELECT`ed the whole row it had just written — all columns, `Scene.contents`
  included — to read one field back. `SanitizesRichHtml` is a set-mutator, so the in-memory
  attribute already holds exactly what was stored; the round-trip returned the same string.
  Reading it in memory is also the safer answer for the response hash, since a `fresh()` between
  a concurrent writer's save and this one would hash a value this request never stored. Same
  change in `RevisionReverter::restore()`.
- The autosave response reported `revision_id` by re-querying for the newest revision, though
  `record()` had just returned that exact row. It is reused, and the lookup kept only for the
  no-op branch where nothing was recorded. Reuse is also the more precise answer: the lookup
  breaks a same-second tie by `created_at` alone, and an autosave burst plus the Save after it
  land in the same second.

### Added

- Tests pinning both: that the response reports the *sanitized* value and hash (the contract that
  stops a rich field's second autosave from 409ing forever), and that `revision_id` names the
  revision just written — or, on a byte-identical resend, the one that already holds the text.

## 2026-07-26 — Strip and fold each searched field once (#52)

### Changed

- Search stripped and accent-folded the same text two or three times per matching entity: the
  membership check folded every field, then the row builder stripped them all again and folded
  each one once more. For a matching scene that was up to a megabyte through
  `RichText::toPlainText()` and `AccentFolder::fold()` twice over, per search. `ProjectSearch`
  now derives both maps once per entity — folded text for comparison, plain text for the
  preview — and passes them down. Search terms are folded once per search rather than once per
  entity per field. No change to what matches.

### Added

- A test that the result preview keeps the writer's own accents and casing while matching stays
  accent-insensitive. The two texts now travel side by side, so handing the folded one to the
  snippet builder is a one-word mistake, and every existing accent test asserts only that the
  row came back.

## 2026-07-26 — Undo a save without reading every word of it (#51)

### Fixed

- "Undo this save" fetched its revision group with a bare `select *`, so undoing a save of a
  scene's contents read up to a megabyte of stored text per row in order to look at four scalar
  columns. The group is read for the morph target, the origin and the `(created_at, id, field)`
  ordering; the one stored value an undo needs belongs to a *predecessor* row and has always
  come from its own query. This was the single place the feature's "list and whole-save queries
  never hydrate `value`" rule was not held — a query-listener test in `RevertSaveTest` now holds
  it there too.

## 2026-07-26 — Make Save and autosave agree on how long a field may be (#50)

### Fixed

- Autosave and the Save button validated the same field two different ways for twelve of the
  fourteen autosaved fields. A writer could autosave 40,000 characters of `dedication` and then
  be told by Save that it "must not be greater than 20000" — about text the server had already
  stored. `Scene.contents` drifted the other way: no cap at all on the Save path against
  autosave's 1,000,000, so an over-long paste was accepted once and refused by every autosave
  after it. Every Form Request now takes its rule from
  `AutosavableFields::validationRule()`, the same source the autosave endpoint uses.
- The Save path now caps fields it previously left unbounded: `description` on all six models
  (100,000) and `Scene.contents` / `Scene.notes` (1,000,000 / 100,000).

### Changed

- Front and back matter (`dedication`, `acknowledgements`, `preface`, `postface`) is capped at
  20,000 characters on both paths — the Save form's long-standing limit wins, and autosave
  tightens to match. The caps now live in `config/revisions.php` beside the others.

### Added

- `FormRequestCapAgreementTest` — walks the registry and fails if any Form Request validates an
  autosaved field differently from `validationRule()`, so the next field added cannot drift the
  same way. Covers the Store requests too, which had the same gap.

## 2026-07-26 — Extract the duplication the week's PRs left behind (#49)

### Added

- `app/Http/Controllers/Concerns/ResolvesIndexSorting` — the `?sort=`/`?direction=` allow-list
  every entity index re-typed. `$sort` reaches `orderBy()`, so that check is a security
  boundary and now has one home.
- `ReordersSiblings`, `ReparentsChildren`, `RedirectsAfterSave` — the authorize-then-move pair,
  the "move or delete" reassignment algorithm (with its two pitfalls explained once instead of
  twice), and the Save / Save-and-stay redirect with its `status=saved` flash.
- `Project::chapterQuery()` / `Project::sceneQuery()` — the `whereHas('act', …)` walk that was
  spelled out at nine call sites. Builders rather than `hasManyThrough`: a join brings `acts`'
  own `name`/`position` into scope, making `orderBy('position')` ambiguous.
- `Act::scenes()` — a real `hasManyThrough` for the grandchildren the cascade-delete summary counts.
- `<x-icon-button>` — the single home of icon-control shape, colour variants and accessible
  name; plus `<x-icon-move-button>` (replacing the byte-identical up/down pair) and
  `<x-icon-dialog-button>` for a delete that opens the move-or-delete dialog.
- `IconButtonComponentTest` and `BladeComponentCompilationTest` — the first tests to assert
  these components' rendered markup at all, and a guard that no page emits an uncompiled
  component tag.

### Changed

- `ProjectSearch::search()` — six near-identical blocks, each passing its field map to two
  methods, collapsed onto one `searchEntity()` helper.
- The `ghost` icon-button variant styles its own disabled state via `disabled:` utilities, so
  the greyed look follows the actual `disabled` attribute — the Story overview's AJAX reorder,
  which toggles it from JS, restyles for free instead of needing its own class list.

### Fixed

- The Acts and Chapters index pages hand-rolled a `<button>` with `icon-delete-button`'s
  classes copied in, which could drift from it; both now compose the shared component.

### Removed

- `icon-move-up-button` / `icon-move-down-button` (superseded by `icon-move-button`), and
  `SceneController::chapterQueryFor()` (now `Project::chapterQuery()`).

## 2026-07-26 — Documentation you can actually read (#48)

### Added

- `documentation/revisions.md`, `documentation/codex.md`, `documentation/epub-export.md` — the
  three biggest features' full references (invariants, pitfalls, rejected alternatives), each
  restructured into scannable lists.
- `documentation/architecture.md` → *The rest of the documentation*: a table mapping every
  page in `documentation/` to what it covers.
- `tests/Unit/DocumentationLinksTest` — guards the docs' cross-references: every relative
  `.md` link resolves, every `#anchor` matches a real heading, and every page is listed in
  the architecture index. A heading moving between pages used to break links silently.

### Changed

- `documentation/architecture.md` is the map, not the manual: Revisions, The Codex and EPUB
  export are now short entry-point sections linking to their deep dives. The file drops from
  1189 lines to 532.
- `documentation/rich-text.md` and `documentation/export-format.md` converted from paragraph
  prose to lists and tables.
- `CLAUDE.md` gains a **Verbosity** rule: lists by default, prose only to explain *why*, no
  restating code, short entry point + linked deep dive.
- `plan-implementer` and `ship-plan` now treat `resolution-log.md` as an exception log rather
  than a work journal — a task that went to plan gets no entry.

## 2026-07-26 — Say why a revert was refused, and never half-apply one (#47)

### Fixed

- **A revert that can't be restored now says so.** Old text is re-checked against the rules
  the field enforces *today* before it goes back — rules can have tightened since it was
  saved, and an old value must not get in through a door a normal save would have closed.
  When that check refused, the page simply came back unchanged: no message, no explanation,
  nothing to act on. It now says what happened, which rule refused it, and that your text is
  still in the history and nothing was changed.
- **A revert can no longer half-happen.** Putting the value back and recording that it went
  back are now one operation. If the second half failed, the text changed with nothing in
  the history saying so — the one outcome this whole feature exists to prevent.

## 2026-07-26 — Document the revision rework (#46)

### Changed

- The revisions chapter of the architecture guide now describes the feature that exists.
  It opens by naming the two altitudes it works at — storage is one immutable row per
  field, everything you see is a **save point** — because almost every confusing thing
  about the code follows from that one split. Added: the routes table with the two legacy
  redirects, why reading history authorizes `view` while reverting authorizes `update`,
  why the visual diff engine is written in-house (and which packages were rejected, and on
  what licence), what a coalescing autosave does to the save point it lands in, and where
  the three entry points into history are.
- Answered "why is my history empty?" in the place someone will look for it: the
  save-grouping migration clears the table, and history restarts from a fresh baseline.
- The glossary gained the terms this feature made load-bearing — save point, snapshot,
  source vs visual diff, hunk, compute-at-write, boundary row, combobox.
- Best practices gained an entry on derived, precomputed columns: when storing an answer
  beats computing it, and the upkeep that buys.

### Fixed

- Two stale claims in the best-practices guide: that Acts, Chapters and the Story overview
  have no feature tests (they have had dedicated ones for a while), and the old changelog
  convention, which described a single `[Unreleased]` list rather than the dated per-PR
  sections this file has used since July 17.

## 2026-07-26 — A way in to history from everything you edit (#45)

### Added

- **A *History* button on every screen that keeps history** — project, act, chapter, scene,
  plotline, event and codex entry. It opens that item's whole history, so "what have I done
  to this scene" no longer starts with picking a field.
- **The item's name in the history sidebar is now a link**, and goes to the same whole-item
  history. The field entries beneath it still narrow that page to one field.

### Changed

- The history sidebar highlights the item's own name when you are looking at its unfiltered
  history, the same way it already highlighted a field you had picked.

## 2026-07-25 — Undo a whole save (#44)

### Added

- **Undo this save.** Every save in a history now has an *Undo this save* button: one
  click puts every field that save changed back to what it was before it, and lands you on
  the edit form looking at the restored text. Fields that save didn't touch are left
  exactly as they are — later edits elsewhere are never discarded.
- The undo is recorded as a save of its own, so nothing is deleted and you can undo the
  undo. That includes undoing your most recent save, which is usually the one you want.
- If anything the save touched changed while you had the page open, the whole undo is
  refused and nothing is written — a half-undone save is worse than none.

## 2026-07-25 — Revert conflicts come back to the page you were reading (#43)

### Changed

- **Reverting a value that changed while you had the page open no longer dumps you on an
  error page.** You come back to the page you were on, with an alert explaining that
  something else changed it — nothing is written. The check itself is unchanged: it is what
  stops a revert from quietly overwriting text you never chose to discard.
- A successful revert now says so. The confirmation was being sent and never displayed.

## 2026-07-25 — Browse history and compare saves, not fields (#42)

### Added

- **The history page now lists saves, not field revisions.** One Save that changed three
  fields is one entry listing all three, with a one-line summary of what changed in each.
  Filter by field, search labels, or show only the saves you made deliberately — all four
  controls (including the page) live in the URL, so a filtered view can be bookmarked and
  shared.
- **Compare now answers "what is different about this scene between these two moments"**,
  covering every field rather than one at a time. A field that neither save touched
  directly still shows up when something changed it in between, because the page compares
  two states of the entity, not two lists of edits. Fields that are the same at both
  moments collapse into a single line.
- Each side of the comparison has a save picker with its own filters — manual saves only,
  and a date range — which are deliberately independent, so a suspect save can be compared
  against the autosaves around it. It works as a plain dropdown with keyboard alone, and
  with JavaScript off.

### Changed

- Diffs are styled in one place, so a rich text field and a Markdown field read as the
  same feature. Every change is marked three ways at once — colour, a `+`/`−` sign, and
  text for screen readers — because colour on its own is not information.
- The old per-field history and compare URLs redirect to the new pages with that field
  pre-filtered. Existing links and bookmarks keep working.

## 2026-07-25 — Read revision history as save points (#41)

### Added

- Revision history can now be read the way it was made: as **save points**. One Save that
  changed three fields is one entry, listing all three, instead of three separate entries
  that happen to share a timestamp. Groundwork for the history and compare screens — no
  page shows this yet.
- Comparing two save points answers "what is different about this scene between these two
  moments", covering every field — including one that neither save touched directly but
  that changed in between. Fields that are the same at both moments are skipped entirely
  rather than compared and found equal.

## 2026-07-25 — Record what each save changed (#40)

### Added

- Every revision now stores a one-line summary of what it changed, and a count of how
  many changes it made, written at the moment the revision is. The upcoming history
  screens read those columns instead of comparing revisions while you wait, so a long
  history opens as fast as a short one.
- The summary shows the *first* change with as much of its surroundings as fits, and
  reports the rest as a count. A find-and-replace across forty paragraphs is one readable
  line plus "and 39 more changes", not forty lines of noise.

### Changed

- Importing a project recomputes each revision's summary as it replays the history, so
  imported history reads exactly like history written here. Summaries are never carried
  in an export archive — they are derived from the values already in it.

## 2026-07-25 — Show what changed in rich text fields (#39)

### Added

- Comparing two revisions of a **rich text field** (act/chapter/scene descriptions, the
  character and place sheets — anything edited in the WYSIWYG editor) now shows the
  field the way it is written, with the changed words marked in place, instead of a
  two-column table of stripped-down plain text. New in-house diff engine under
  `App\Services\Diff\`: paragraphs are matched first, then words inside the paragraphs
  that actually changed. No new dependency — it is built on the sequence matcher the
  existing diff library already ships.

### Changed

- A save that only changed formatting — bolding a sentence, turning a paragraph into a
  heading — now shows what changed. It used to say *"Formatting changed only."* and show
  nothing, because rich fields were flattened to plain text before diffing.
- Markdown and plain fields (`Scene.contents`, the project front/back matter, the rights
  notice) keep their existing side-by-side source diff. There the markup is what the
  writer typed, so it has to stay visible.

## 2026-07-25 — Group revisions into save points (#38)

### Added

- Revision rows now carry a **save point** id (`save_id`): every field written by one
  Save — or by one autosave burst — shares it. This is the unit the upcoming history,
  compare and revert screens address, instead of addressing single field revisions.
  Rows also gained `summary_html` / `change_count` columns, filled in a later change,
  so a history list never has to compute a diff to render itself.
- Export archives carry `save_id` in each `revisions/<field>.json` sidecar, and import
  remaps every source group to a fresh local group — so "these rows were one save"
  survives a round-trip without borrowing another install's ids.

### Removed

- **All existing revision history is deleted** when this migration runs. Rows written
  before save points existed have no group to belong to, and a null grouping key would
  poison every read path. History restarts from a fresh baseline the next time each
  field is edited. Safe here because the project is pre-V1 and the only data in
  existence is the demo seed.

## 2026-07-25 — Silence the unsaved-changes prompt on Save (#37)

### Fixed

- Clicking **Save** or **Save and stay** on an entity edit page no longer triggers the
  browser's native *"…information you've entered may not be saved"* prompt. Those buttons
  now carry `data-guard-save`, and the `beforeunload` unsaved-changes fallback in
  `navigation-guard.js` stays silent while their submit is in flight (and fires
  `autosave:explicit-leave` so each field skips its now-redundant draft mirror). Every
  other form submit on the page still warns/behaves exactly as before.

## 2026-07-24 — Revisions sidebar polish (D2/D3) (#33)

### Added

- The revisions-browser sidebar now has a client-side **filter box** that narrows the
  list by entity name as you type (matching groups auto-expand, the rest hide), and each
  group heading carries a **count badge** of the revised entities it holds.

### Changed

- Sidebar groups now **default-collapse**: only the group holding the entity currently
  being viewed starts open, so a heavily-revised project opens compact.

### Removed

- The per-field **field switcher** on the history page. The sidebar is now the single
  per-field navigation surface; reaching a sibling field that has no revisions yet goes
  back through the edit page.

## 2026-07-24 — Revision reads authorize `view` (C1) (#32)

### Changed

- Revision **reads** (history, compare, and the project revisions browser) now authorize
  `view` on the owning project instead of `update`; the mutating **revert** and **purge**
  actions still require `update`. Reading history is a view capability — the altitude is
  now set deliberately (one level below the writes) rather than inherited by accident. In
  this single-owner app `view` and `update` resolve to the same user, so there is no
  behavior change today; it makes a future view-only collaborator possible.

## 2026-07-24 — Centralize revisionable display-name (A3) (#31)

### Changed

- Centralized "how a revisionable is titled" (`name` for all but Event, which uses `title`)
  into `HasRevisions`: a static `revisionDisplayColumn()` — overridden once on `Event` — and
  an instance `revisionDisplayName()` reading it. `RevisionController` (history/compare
  heading) and `ProjectRevisionsBrowser` (sidebar) both go through it, removing the
  duplicated `event → title` special-case from those two files.

## 2026-07-24 — Manual-save revision trait & default label (A1) (#30)

### Changed

- Extracted the manual-save revision checkpoint every entity controller's `update()`
  repeated into a shared `App\Http\Controllers\Concerns\RecordsManualRevisions` trait
  (`snapshotAutosaved()` before the save, `recordManualSave()` after). All 7 controllers
  (Project, Act, Chapter, Plotline, Event, Scene, CodexEntry) now go through it, and none
  of them inject `RevisionRecorder` or name the label anymore — the `$model->update()`
  itself is intentionally left per-controller (cover images, transactions, reparenting all
  genuinely differ). `RevisionRecorder::recordManualChanges()` now defaults its `$label`
  to `manualSaveLabel()`, the only label any caller ever passed.

### Fixed

- Corrected the *Revisions* architecture doc's "byte-identical no-op" note, which still
  described the `manual=true` autosave bypass removed in #28.

### Changed

- Centralized the autosave/revisions slug+field→model resolution in
  `AutosavableFields::resolveField()`. `FieldAutosaveController` and `RevisionController`
  each used to re-derive `REGISTRY[$slug][1]` and `abort_unless(array_key_exists(...), 404)`
  independently; the shared resolver is now the single home of the "unknown field 404s"
  contract, so the two paths can never drift.

## 2026-07-24 — Revisions/autosave dead-code cleanup (#28)

### Removed

- The unused `manual` flag on the autosave PATCH endpoint and in `field.js`. Nothing ever
  sent `manual: true` — the full-form Save button's permanent, labeled manual checkpoint is
  recorded server-side by the entity controllers (`RevisionRecorder::recordManualChanges`),
  not through this endpoint. The autosave endpoint now always records `origin: automatic`
  and skips the write on a byte-identical no-op, as before.

### Changed

- Simplified `<x-autosave-field>`: dropped the always-true `Route::has()` guards around the
  History / Compare links (those routes have existed since the feature shipped).
- Refreshed a stale `config/revisions.php` comment — the `RevisionSetting` retention
  singleton exists and is what `Revision::prunable()` reads at prune time.

## 2026-07-24 — Revisions browser, readable compare & manual-save labels (#27)

### Added

- A **Tools** dropdown in the main toolbar, with a **Revisions** entry that opens a
  project-scoped revisions browser (`RevisionBrowserController`, route
  `projects.revisions.index`). Its sidebar lists every entity and field in the project
  that actually has revision history — grouped by type, each field showing its revision
  count — so the whole project's history is reachable from one place instead of only the
  History icon on each field's own edit page.
- `App\Services\ProjectRevisionsBrowser`, the single query owner behind the sidebar tree
  (one grouped query over `revisions.project_id`; never hydrates `value`), and a shared
  `<x-revisions-layout>` shell so the history and compare pages keep the sidebar in view
  while drilling in.

### Changed

- The compare view no longer renders the diff as a bordered two-column table. It is now a
  clean, borderless **Old / New** side-by-side that reads like prose, with only the
  changed words tinted (red on the old side, green on the new).

### Fixed

- The full-form **Save** button on every autosaved entity (Project, Act, Chapter,
  Plotline, Event, Scene, Codex entry) now records a permanent, labeled manual revision
  (`"Saved <date>"`) for each field that actually changed — previously the Save button
  wrote the column but created no manual checkpoint, leaving only the periodic
  `origin: automatic` autosave rows (subject to coalescing and pruning). Only fields whose
  value changed get a row, so saving a multi-field form after editing one field no longer
  spams near-empty revisions for the rest.

## 2026-07-24 — Autosave storage improvements (#26)

### Changed

- Autosave drafts are now written to `localStorage` once, at `beforeunload`, instead of
  on every keystroke — the write is also suppressed when the writer explicitly confirmed
  leaving via the navigation guard, and skipped entirely once a draft is more than 4
  hours old (a flat TTL, not reset at midnight).
- Draft recovery moved from an inline per-field banner to a single page-level modal
  (`Alpine.data('draftRecoveryModal')`) that lists every dirty field's unsaved draft at
  once, with Restore/Discard per entry or all at once — closing the modal (Esc/backdrop)
  never discards a draft, only an explicit action does.

### Removed

- The old inline per-field draft-recovery banner and its backing Alpine state
  (`draftAction`/`draftValue`/`draftSavedAt`/`restoreDraft()`/`discardDraft()`/
  `checkForDraft()` in `field.js`), superseded by the new global recovery modal.

### Added

- Data loss warnings: an in-app "You have unsaved changes — leave anyway?" dialog now
  intercepts navigation away from a dirty autosave field, and a native `beforeunload`
  prompt catches an actual tab-close/browser-quit — neither existed before, so closing a
  tab mid-edit previously lost the text with no warning at all. Deleting an Act or
  Chapter that still has chapters/scenes underneath now offers a choice — move them to
  another act/chapter (reassignment + delete happen as one transaction) or delete
  everything, with an honest count either way — instead of a generic "are you sure?"
  that gave no hint of the blast radius or any alternative to losing them. Deleting a
  Project (whose children have no natural place to move to) keeps a plain confirmation,
  now naming only the non-zero categories being removed.

### Changed

- `Alpine.store('autosave')` gained a `dirty`/`isDirty()` signal, distinct from each
  field's save-state machine — a field counts as dirty from the first keystroke through
  the ~2s autosave debounce window, which is exactly the gap the new navigation guard
  and `beforeunload` fallback both close.

## 2026-07-24 — Autosave with revisions (#24)

### Added

- Autosave with revisions: the 14 registered long-text fields across the project tree
  (`Scene.contents`, act/chapter/plotline/event descriptions, project description and
  front/back matter, codex entry descriptions) now save automatically via AJAX as the
  writer types — debounce, blur, and `Ctrl-S`/`Cmd-S` all flush a pending save — instead
  of only on an explicit form submit. A crashed or closed tab's unsaved text survives in
  `localStorage` and is offered back on next load, and a stale two-tab overwrite is caught
  by a 409 conflict response (Reload / Keep mine / Compare) rather than silently clobbering
  the other tab's work.
- A per-field revision history page (`RevisionController`) with a word-level compare view
  and a non-destructive revert — reverting always writes a new `origin: revert` row, it
  never deletes history. Revisions coalesce within a configurable per-field window so a
  run of keystrokes doesn't produce one row per autosave tick, while a manual Save always
  stays individually visible.
- A daily automatic prune (`Revision::prunable()`, wired into `model:prune`) keeps the
  `revisions` table bounded, with a non-negotiable rule that it never removes a labeled
  revision, a non-automatic-origin revision, or the newest revision of any field. A new
  admin "Revisions" settings page exposes the retention window (confirm-gated when
  lowering it) and a storage panel with category/age-based bulk purge
  (`RevisionPurger`, `revisions:purge`).
- Project export/import now carries revision history: a new `include_revisions` toggle on
  export writes each field's history to a `revisions/<field>.json` sidecar per entity, and
  import restores it tagged `origin: import` (never eligible for the automatic prune).
  EPUB export is unaffected — it never included revisions and still doesn't.

### Changed

- Adding autosave to a future long-text field is now a one-registry-entry
  (`App\Support\AutosavableFields::REGISTRY`) + one-Blade-line (`x-autosave-field`)
  change, not a new controller/route/test per field — see `documentation/architecture.md`
  → "Revisions".

## 2026-07-22 — Reorganize the WYSIWYG toolbar into labeled clusters (#21)

### Changed

- The rich-text editor's toolbar (`x-wysiwyg`) now groups its ~25 buttons into five labeled
  clusters — Headings, Text format, Lists & blocks, Insert, and Table structure — instead of one
  long flex-wrapped row with only loose divider ticks. Headings and Table structure, the two
  least-frequently-used clusters, collapse behind a dropdown (reusing the existing
  `<x-dropdown>` component) so the always-visible row roughly halves in button count. Every
  command, its keyboard/click behavior, and the existing HTML-mode-only gating (merge/split,
  table row/column ops) are unchanged — this is a presentation refactor only.
- Extracted a new `x-wysiwyg.toolbar-button` sub-component for the plain toggle-button shape
  (`cmd()` + optional `isOn()`), so adding a future toolbar command is a one-array-entry change
  rather than copy-pasted button markup.

## 2026-07-21 — Number the remaining changelog headings (#20)

### Fixed

- The two `2026-07-17` sections gained their PR numbers — accent-insensitive advanced search
  is #10 and the rich-text preview tag-stripping is #9, both matched against `gh pr list`
  rather than inferred from position. Every section dated `2026-07-17` or later now names
  its PR.

### Changed

- The changelog's own header now explains that a section *without* a `(#PR)` suffix landed
  directly on `master` before the protected-branch workflow existed, rather than being an
  oversight. Two sections are in that category — **Advanced search** (`2026-07-16`) and the
  **Laravel 13 / PHP 8.5 upgrade** (`2026-07-15`) — and they are correct as they stand;
  without the note there is no way to tell a deliberate absence from a forgotten number.

## 2026-07-21 — File the stranded changelog entries under their own PRs (#19)

### Fixed

- `[Unreleased]` had been holding the **Configurable EPUB export** entries since they merged
  in #12, contradicting the convention's own rule that the section carries only work not yet
  on `master`. They now sit under a dated `2026-07-18` heading. A second section had also
  collected two unrelated PRs at once — the EPUB `short_open_tag` fix (#14) and the Docker
  environment work (#13) — which showed up as a section with duplicate `### Changed` and
  `### Fixed` groups; it is split in two, and all three headings gained their PR numbers.
  No entry text was reworded: the change is purely which heading each entry files under.

## 2026-07-21 — Stamp the PR number onto changelog headings automatically (#18)

### Fixed

- `scripts/pr-land.sh` now writes the `(#PR)` suffix onto the newest dated `CHANGELOG.md`
  heading itself, between `gh pr create` and arming auto-merge. The convention asks each
  dated section to name the PR that shipped it, but the number does not exist until the PR
  is opened — and by then the entry is already committed — so every entry needed a manual
  follow-up (#16 was one). The stamp only ever touches the *first* dated heading (new
  entries go on top, so that is the current PR's), skips a heading that already carries a
  number, and is skipped entirely when the branch does not touch `CHANGELOG.md` — so it
  cannot retro-fit a wrong number onto an older entry.

### Changed

- The ship-pr skill now instructs leaving the `(#PR)` suffix off when writing the entry,
  and `scripts/README.md` records the new step.

## 2026-07-21 — Force a patched `shell-quote` via an npm override (#17)

### Fixed

- Forced `shell-quote` to `^1.9.0` with an npm `overrides` entry, clearing the high-severity
  [GHSA-395f-4hp3-45gv](https://github.com/advisories/GHSA-395f-4hp3-45gv) / CVE-2026-13311
  advisory (quadratic-complexity denial of service in `parse()`). The package reaches us only
  through `concurrently`, which pins it *exactly* at the vulnerable `1.8.4`, so upgrading
  `concurrently` could not fix it — even its latest release still names `1.8.4`. Real exposure
  was minimal: it is a dev-only dependency used solely by the `composer dev` script, on
  hardcoded (not attacker-controlled) command strings, and it ships in neither Docker image.
  The override mainly stops a permanently red security alert from training everyone to ignore
  the next one.

### Added

- New [`documentation/dependency-overrides.md`](documentation/dependency-overrides.md)
  explaining what an npm override is, why this project carries the `shell-quote` one, when an
  override is the wrong tool (it gives a package a version it never tested against, and the
  failure surfaces in someone else's stack trace), and how to retire one once upstream fixes
  its own tree.

## 2026-07-21 — Upgrade the test toolchain to PHPUnit 13 and ParaTest 7.23 (#15)

### Changed

- Upgraded the development test toolchain to PHPUnit 13.2 and ParaTest 7.23 (from PHPUnit
  11.5 / ParaTest 7.8), pulling ~23 transitive `sebastian/*` and `phpunit/php-*` majors with
  them. ParaTest could not move past 7.8 while the PHPUnit constraint stayed on `^11.5`
  (ParaTest 7.23 requires `phpunit/phpunit ^13.2`), so the two only move together.
  `composer test` is unchanged and the suite passes as-is — no test, `phpunit.xml`, or
  assertion changes were needed, and PHPUnit 13 raised no deprecation or schema warnings.
  Both are dev-only dependencies, so the production image (`composer install --no-dev`) is
  unaffected.

> [!NOTE]
> After pulling this, Docker users need `make rebuild` rather than `make build` — `vendor/`
> lives in an anonymous volume that Compose carries over to a recreated container, so a plain
> build leaves the old PHPUnit 11 mounted on top of the new image.

## 2026-07-21 — EPUB export: XML declaration and config layout (#14)

### Fixed

- **The EPUB export could not render under `short_open_tag=On`.** The layout emitted its
  XML declaration as `{!! '<?xml …' !!}`, but Blade runs PHP's own lexer over the raw
  template before compiling `{!! !!}` into an echo — so `<?xml` was read as a short open
  tag during that pass and the build failed with `unexpected identifier "version"`,
  regardless of the `{!! !!}` wrapping. The declaration is now built by concatenating
  `'<'` with the rest, which never puts the two characters adjacent in the source.
  This surfaced in Docker: the official PHP images ship no `php.ini`, so PHP's compiled-in
  default (`short_open_tag=On`) applies there, while a typical host `php.ini` sets it Off.

### Changed

- The EPUB publication-settings form is grouped in a labelled box showing the loaded
  project's name, so it is clear which project the settings belong to, and the project
  picker's **Load** button is promoted to the primary style as the page's entry action.

## 2026-07-21 — Docker environment fixes and dependency refresh (#13)

### Added

- **Docker support.** Production (`Dockerfile`, `docker-compose.yml`) and development
  (`Dockerfile.dev`, `docker-compose.dev.yml`, `Makefile`) container setups, so the app
  can run without a local PHP/Node install. Documented in `documentation/docker.md`;
  `docker/entrypoint.sh` generates a fresh `APP_KEY` per instance on first boot rather
  than shipping a shared one.

### Changed

- **Composer dependencies refreshed** within the existing constraints (Laravel 13.20.0 →
  13.21.1, Guzzle 7.14.2 → 7.15.1, plus patch bumps). PHPUnit deliberately stays on
  `^11.5.50`; `brianium/paratest` is pinned behind it (7.23.0 requires PHPUnit `^13.2`),
  so those two must be upgraded together in their own change.

- **Docker: MailHog replaced with Mailpit.** MailHog has been unmaintained since 2020;
  Mailpit is a drop-in replacement on the same SMTP port, with the UI still at `:8025`.

### Fixed

- **Docker: Xdebug was installed but could never connect.** The image enabled the
  extension without configuring it, so it ran in the default `develop` mode — which does
  not include step debugging — and the compose file published port 9000 (Xdebug 2's
  default, and php-fpm's own port) even though Xdebug 3 dials *out* to the IDE. New
  `docker/xdebug.ini` sets `mode=debug` on port 9003 via `host.docker.internal`, with
  `start_with_request=trigger` so tests aren't slowed when not debugging.

- **Docker: mail was misconfigured in development.** `MAIL_MAILER=mailhog` is not a
  Laravel mail driver (`config/mail.php` defines no such transport), so sending mail from
  the dev container raised `Mailer [mailhog] is not defined`. Now `smtp` pointed at the
  mail catcher.

- **`composer test` failed inside the Docker container** with 248 failures that did not
  reproduce on the host. PHPUnit's `<env>` entries do not override variables already
  present in the real environment unless marked `force="true"`, so the container's
  `APP_ENV=local` beat `phpunit.xml`'s `APP_ENV=testing`. `ValidateCsrfToken` only skips
  itself in the `testing` environment, so CSRF was enforced and every write request in the
  suite returned 419. All `phpunit.xml` env entries are now forced, making `composer test`
  behave identically on the host, in the container, and in CI.

- **`make clean` could never run on Windows.** Make executes recipes through `cmd.exe`
  there, where the target's `rm -rf` does not exist, so the command aborted. It now
  selects `del` or `rm` from the `OS` variable. `documentation/docker.md` records the
  constraint, since it applies to any shell command added to a target.

- **Docker: `make rebuild` silently kept stale dependencies.** `vendor/` and
  `node_modules/` are anonymous volumes, and Compose carries those over when recreating a
  container — so rebuilding after a `composer.json`/`package.json` change left the old
  dependencies mounted over the new image. `make rebuild` now recreates with
  `--renew-anon-volumes`.

### Removed

- **Docker: the Redis container.** Cache, sessions, and the queue all use database drivers
  whose tables ship with Laravel's default migrations, so Redis was a second service doing
  nothing at this scale (`CACHE_STORE` is now `database`). `config/database.php` still
  reads the `REDIS_*` variables if a deployment later needs it.

- **Obsolete Compose `version:` key** from both compose files, and the end-of-life
  `docker-compose` (v1) invocation in the `Makefile`, now `docker compose`.

## 2026-07-18 — Configurable EPUB export (#12)

### Added

- **Configurable EPUB export.** The ebook export (**Admin → Export & import → Export →
  Ebook**) is now driven by a per-project **publication settings** form, so an author controls
  what the generated `.epub` contains instead of taking one fixed layout. Every option defaults
  to reproducing the previous export exactly, so an untouched project downloads the same book as
  before. The config page offers:
  - **Metadata toggles** — include (or omit) the author, publisher, rights, and ISBN in the
    book's Dublin Core metadata.
  - **Cover toggles** — include the project cover, and optionally a full-page **cover image per
    chapter** (uploaded on the chapter edit page).
  - **Content options** — show scene titles; show act / chapter / scene descriptions; and pick
    the chapter-heading format (e.g. `Chapter 1: Title`, just the title, or just the number) and
    the scene **divider** style (horizontal rule or a decorative flourish).
  - **Front & back matter** — four optional Markdown sections (dedication, acknowledgements,
    preface, postface) written on the project edit page, each independently toggled into the
    book. Their order relative to the table of contents and the story body is set with a sortable
    **section order** list (move up / move down), with the title page pinned first.
  - **Table-of-contents depth** — list acts only, acts with their chapters (the default), or a
    third level of per-scene links.
  - **Codex appendix** — an optional back-matter appendix built from the project's Codex:
    choose which entry types to include (characters / locations / organizations) and whether to
    embed each entry's first image, rendered as a heading page plus one page per entry.
- Projects now carry four optional front-/back-matter Markdown fields — dedication,
  acknowledgements, preface, postface — editable on the project edit page.
- **The archive round-trips all of it.** The publication settings travel in the `.zip` export
  (`data/publication-setting.json`), the four Markdown fields and any chapter cover images travel
  in `data/`, and all of it is restored on import. The export manifest `version` bumps to `2`
  once to cover every new field; version `1` archives still import cleanly, with the new fields
  left `null`/default. The publication settings are validated as **untrusted** input against the
  same rules as the config form: a malformed setting is logged, skipped, and the project imports
  its content on the default settings rather than failing the whole import (unknown appendix codex
  types are dropped individually). A project with no saved setting omits the descriptor and
  round-trips to the lazy default.

### Changed

- The Export & import "Export" and "Import" screens are now three server-rendered pages with a
  sub-navigation (Export / Ebook / Import) instead of a single Alpine-tabbed page; the sidebar
  entry and route names are unchanged.
- The EPUB export architecture and the new archive fields are documented in
  `documentation/architecture.md` (→ *EPUB export (publication settings)*) and
  `documentation/export-format.md`.

## 2026-07-17 — Accent-insensitive advanced search (#10)

### Added

- Project search now matches across accents: searching `Melusine` finds a `Mélusine`
  character (and the reverse), for every searchable field of every entity. A new
  `App\Support\AccentFolder` is the single source of truth — one 1:1 accent→base map, applied
  at the entity gate, the per-field label check, and the snippet highlighter so all three agree.

### Changed

- Search matching now runs in PHP (accent-folded `str_contains`) rather than a SQL `WHERE`
  clause, making it identical and portable across every supported database driver (a folding
  SQL expression is not — it overflows SQLite's parser on some builds). Snippet highlighting
  matches on accent-folded text but still renders the original accented characters inside
  `<mark>`, and matching is now uniformly case-insensitive across all drivers.

## 2026-07-17 — Strip HTML tags from rich-text field previews in search results (#9)

### Fixed

- Search result previews for rich-HTML fields (`Scene.notes` and any `description` field
  per `RichTextFields`) no longer show raw or HTML-escaped markup (e.g. `&lt;p&gt;`) — the
  preview now shows the reader's plain text, matching what other rich-text renders already do.

## 2026-07-17 — Extract workflow tooling from the skills (#7)

### Added

- `scripts/` toolbox extracted from the `.claude/` skills by the reworked
  `extract-tools-and-commands` skill: `spec-locate.sh`, `spec-advance.sh`,
  `plan-next-task.sh` (the `.specs` lifecycle mechanics), `serve-app.sh` /
  `stop-app.sh` (PID-file-based dev-server control with pre-flight checks), and
  `pr-land.sh` (push → PR → auto-merge → confirm-merged), each following a documented
  bash contract; indexed in `scripts/README.md`.
- `php artisan spec:draft` command scaffolding a stage-1 draft spec (prompts for
  missing input interactively, non-interactive for agents), with `config/specs.php`
  making the `.specs` base path injectable and a 6-test feature test.

### Changed

- `extract-tools-and-commands` skill elaborated from a four-line brief into a recurring
  extraction pass with selection criteria, an artisan-vs-bash decision rule, a bash
  script contract, and an audit → propose → approve → extract → rewire procedure.
- The mp-spec-expander, plan-tasks, ship-plan, ship-pr, draft-spec, and run-imagoldfish
  skills and the plan-implementer agent now delegate their mechanical command sequences
  to the extracted tools, keeping only the judgment and invariant rationale inline.

## 2026-07-17 — Month buckets for the .specs tree (#6)

### Changed

- **`.specs/` stages past draft now bucket features by month** —
  `.specs/<status>/<YYYY-MM>/<name>/` — so `shipped/` (21 features after two weeks, and
  the only folder that grows forever) stays listable. Drafts stay flat under
  `.specs/draft/<name>/` since a draft has no lifecycle date yet. Each pipeline stage now
  stamps its date in the spec frontmatter (`expanded:` / `planned:` / `shipped:`) and the
  bucket is that date's month; `SpecsStatusConsistencyTest` enforces the bucket shape,
  the date stamps, and bucket↔stamp agreement, alongside its existing status and
  name-uniqueness checks. The pipeline skills (`draft-spec`, `mp-spec-expander`,
  `plan-tasks`, `ship-plan`) and the `plan-implementer` agent locate features with the
  glob pair `.specs/draft/<name>/` + `.specs/*/*/<name>/`. All 21 shipped features moved
  to `shipped/2026-07/` (two missing `shipped:` stamps backfilled from git history), and
  the live docs that referenced old `.specs/shipped/<name>/` paths were updated.

## 2026-07-17 — PR shipping ritual & dated changelog (#5)

### Added

- **`ship-pr` skill** (`.claude/skills/ship-pr/SKILL.md`): the protected-`master`
  branch → commit → push → PR → squash-auto-merge ritual as one reusable skill;
  `ship-plan` step 9 now delegates to it instead of re-describing the dance.

### Changed

- **This changelog now uses dated sections.** Everything used to pile up under one giant
  `[Unreleased]` heading with no way to tell when an entry shipped. Each merged PR now adds
  its own `## YYYY-MM-DD — <title> (#PR)` section (convention documented in the header and
  `CLAUDE.md`); the existing entries were re-filed under dated headings where attributable
  (2026-07-14 → 2026-07-17) and an "Earlier" section for the rest.
- **Repository auto-merge enabled** (GitHub setting): `gh pr merge --squash --auto` now
  arms a PR to land itself when the `tests` check goes green — no manual watch-and-merge.

## 2026-07-17 — Workflow optimizations (#4)

### Changed

- **Workflow optimizations from the 2026-07-16 tooling audit.** `composer lint` is now the
  canonical Pint entry point (`composer lint -- --test` to check only) — CLAUDE.md,
  `documentation/code-style.md`, CI, and the `run-imagoldfish` skill all point at it. A
  committed project allowlist (`.claude/settings.json`) covers the project's own
  test/lint/artisan/build commands so implementer agent loops stop stalling on permission
  prompts. The `plan-implementer` agent runs on Sonnet by default (`ship-plan` escalates
  gnarly tasks to Opus per task) and no longer pre-reads every expanded spec doc — only
  the ones the selected task file links. The `run-imagoldfish` skill logs in with the
  seeded `admin@example.com` dev user instead of creating and deleting a throwaway user
  via tinker.

### Removed

- **`.claude/guidelines.md`** — it had drifted into a stale subset of `CLAUDE.md` (it still
  claimed there was no `app/Services` layer and no Scene/Act/Chapter feature tests).
  `CLAUDE.md` is the single maintained conventions file; the skills and agents that listed
  both now read only `CLAUDE.md`. Historical references in `.specs/shipped/` are left as-is.

## 2026-07-16 — CI merge gate & parallel test suite (#3)

### Added

- **CI merge gate.** GitHub Actions workflow (`.github/workflows/tests.yml`) runs the
  parallel test suite and `pint --test` on every push and pull request (PHP 8.5,
  ubuntu-latest, real frontend build so `@vite` views render). Branch protection on
  `master` now requires a pull request with a green `tests` check before merging —
  direct pushes are rejected. `pint.json` excludes the two hand-maintained Melusine
  seeder variants so the style check reflects the "never reformat those" convention.

### Changed

- **`composer test` now runs the suite in parallel** (`php artisan test --parallel` via
  `brianium/paratest`, new dev dependency): 4m18s → ~1m08s for 580 tests on the reference
  machine. Each worker gets its own in-memory SQLite database, so tests must not assume
  shared state across classes (they already don't, per `RefreshDatabase`).

## 2026-07-16 — Advanced search

### Added

- **Project-wide search page.** New `GET /projects/{project}/search` (last item in the primary
  nav) scans the string/text fields of Acts, Chapters, Scenes (contents + notes), Events,
  Plotlines, and Codex entries, with three match modes — all words (AND, the default), any word
  (OR), exact phrase — and renders results grouped like the menu: Timeline / Story / Codex
  sections, each stacking one full-width table per entity type, like the entity list pages
  (Codex splits per entry type; an initial 3-column grid was revised away — too narrow to
  read). Each matched *entity* is one table row: its name (linked to the edit page), the
  fields the terms matched in ("Name, Contents"), a ~120-character highlighted text preview
  of the first matching field (`<mark>`, escape-then-highlight so stored markup never renders
  live), and a trailing view button. Entity types with no matches are hidden, as are sections
  whose tables are all empty — only what matched renders. Plain GET form, no
  AJAX; an empty query is the normal landing state, not an error. Under the hood: the first
  `app/Services` service (`ProjectSearch`, a fixed six SELECTs per search with `LIKE`-wildcard
  escaping so literal `%`/`_` in a query match literally), a `SearchMode` enum, and a
  `SearchSnippet` helper — no new package, migration, or index. Result caps/pagination are
  deliberately deferred to the `search_pagination` draft spec.

## 2026-07-15 — Laravel 13 / PHP 8.5 upgrade

### Changed

- **Upgraded to Laravel 13.20.0 on PHP 8.5.7 (WAMP).** Bumped `composer.json` to
  `"php": "^8.5"` and `"laravel/framework": "^13.0"`, and switched WAMP's active Apache
  PHP module from 8.2.18 to 8.5.7. Maintenance upgrade with no behavior change: the only
  dependency the framework bump forced was `laravel/tinker` → `^3.0` (resolved to v3.0.2);
  no `config/*.php` or `bootstrap/app.php` change was required, and `composer test` stayed
  green (539 passed / 2013 assertions). `ext-imap` (removed from PHP core in 8.4) was
  intentionally not restored — the app does not use it.

## 2026-07-14 — Codex alias references (#2)

### Added

- **Manual codex reference resync.** New `codex:sync-references {project?}` artisan command
  rebuilds the `scene_codex_entry` pivot from scratch (every project, or one via the optional
  argument) — needed to backfill scenes that existed before `SceneReferenceMatcher` shipped, since
  normal saves keep the pivot in sync automatically and nothing else ever touches pre-existing data.
  The project edit page gains a matching **"Resync codex references"** button: its own footer form
  (`POST /projects/{project}/codex-references/sync`), separate from the main project-fields form so
  it submits independently, same `update` authorization as the rest of project editing.
- **Scene edit page shows which codex entries it references.** The edit form's sidebar gains a
  **"Codex references"** card listing every codex entry whose name or an alias whole-word-matches the
  scene's contents (as of the last save), a flat list ordered by `(type, name)`; each row links to
  the entry's edit page and shows its type label. A "Detected from the scene contents on last save."
  caption makes the no-AJAX, save-time refresh behaviour explicit. Read-only view of the derived
  `scene_codex_entry` cache; never rendered on the public scene share page.
- **Codex entry edit page shows where each entry is referenced.** The edit form's sidebar gains a
  **"Referenced in scenes"** card listing every scene whose contents match the entry's name or an
  alias, in event-timeline order `(event_datetime, id)`; scenes with no assigned event sort last and
  are labelled "No event assigned". Each row links to the scene's edit page. The aliases field gains
  help text explaining that matching is case-sensitive, whole-word, ignores aliases under 3
  characters, and can be ambiguous when aliases overlap — so a writer can understand why a name
  silently never links. Read-only view of the derived `scene_codex_entry` cache; no AJAX, refreshed
  on save.
- **Codex entry saves now recompute scene references.** Creating a codex entry always runs
  `SceneReferenceMatcher::syncProject()` (a new entry's name/alias set is trivially new), so a scene
  whose contents already mention the entry links immediately with no scene re-save. Editing an entry
  runs the project-wide rescan **only when its matching terms (name plus aliases) actually change** —
  an unrelated edit (new cover image, description tweak) skips the O(scenes) recompute. The
  before/after comparison and the rescan both run inside the entry's existing `DB::transaction`, so
  aliases and references stay atomic. Entry deletion needs no code: `cascadeOnDelete` already drops
  the pivot rows.
- **Scene saves now record codex references.** Creating or updating a scene runs
  `SceneReferenceMatcher::syncScene()` after the row is saved, so the `scene_codex_entry` pivot
  always reflects which codex entries the scene's current `contents` reference (a full resync — no
  stale rows). No "did contents change" skip: a scene save always recomputes its own references,
  mirroring the adjacent `mentionedEvents()->sync()` call.
- **Project import regenerates scene ↔ codex references.** `ProjectImporter::run()` recomputes the
  `scene_codex_entry` cache once via `SceneReferenceMatcher::syncProject()` after the graph-import
  phases finish and before the import is marked completed — the archive never carries this derived
  data, so an imported project ends up with exactly the references a native save would have produced
  (including overlapping-alias links to every matching entry). The hook is not a fifth import phase:
  it runs at the post-loop fall-through reached exactly once per finishing import, and being a full
  idempotent resync it is safe to retry on a resumed run. Confirms the exporter still writes no
  reference data.
- **Scene ↔ Codex reference matcher.** New `App\Services\SceneReferenceMatcher` computes which
  codex entries a scene's `contents` reference — a whole-word, **case-sensitive**, Unicode-aware
  (NFC-normalized) match of each entry's `name` and eligible aliases (aliases shorter than 3
  characters are ignored), persisted as a full `sync()` into the `scene_codex_entry` pivot. Hyphen
  is part of the word ("Jean" does not match inside "Jean-Luc"), and malformed UTF-8 in a scene is
  logged and skipped rather than allowed to block the save. Declares the previously-implicit
  `ext-intl` requirement in `composer.json`. Controller wiring and UI arrive in later tasks.
- **Scene ↔ Codex reference links (data model).** New `scene_codex_entry` pivot table
  (plain link table, composite PK, `cascadeOnDelete` on both FKs — matching the
  `codex_entry_tag` / `event_scene` convention) with `Scene::codexReferences()` and
  `CodexEntry::referencingScenes()` relations. This is the persisted, derived cache of
  "which codex entries a scene's contents reference"; matching logic, controller wiring,
  and UI arrive in later alias-references tasks.

### Fixed

- **English demo data (`MelusineSeederEn`) was missing the aliases scenes actually use.** Mélusine's
  entry only had the accented `Mélusine` name and `Melusina`/`The Serpent Lady`/`Lady of Lusignan`
  aliases, but every scene spells the name **without** the accent (`Melusine`) — a different letter,
  not a normalization difference, so it could never match. Raymondin's entry had `Raymond` as an
  alias, but `Raymond` is a substring of `Raymondin` (the spelling scenes use), not a separate word,
  so whole-word matching correctly refused it. Added `Melusine` and `Raymondin` as aliases so the
  demo project's own scenes link the way a reader would expect (`codex:sync-references` picks up
  existing seeded scenes once these land).
- **French and Italian demo data had the same gap for their Raymondin/Raimondino entries.**
  `Raymond`/`Raimondo` are substrings of the `Raymondin`/`Raimondino` spelling the French and
  Italian scenes actually use, so whole-word matching never linked them (same class of bug as the
  English fix above; the French/Italian Mélusine entries were unaffected — their scenes already
  spell the accented name consistently). Added `Raymondin` to `MelusineSeederFr` and `Raimondino`
  to `MelusineSeederIt`.

## Earlier (shipped before 2026-07-14)

These entries predate the dated-section convention above; their individual ship dates are
in git history (`git log -S "<entry text>" -- CHANGELOG.md`). Grouped by change type.

### Added

- **Project import.** **Admin → Export & import → Import** now reads an export `.zip` back into a
  brand-new project owned by the importing user (the tab previously just said import was "coming soon").
  Import reconstructs the full graph from the archive's lossless `data/` layer — Project, Acts/Chapters/
  Scenes, the Timeline (plotlines + events), and the Codex (entries, aliases, tags, attributes,
  event-anchored attribute values, and media) — remapping every archived id onto fresh rows and replaying
  `position` verbatim. The upload is treated as untrusted: a six-check security gate
  (`ArchiveValidator` — zip validity, zip-slip, an allow-listed arborescence, manifest version, JSON
  shape, and content-sniffed media types) plus a reject-on-violation content sanitizer run before
  anything is written, and the auto-created main plotline / Start/End bookends are reconciled rather than
  duplicated. A name collision only ever renames the new project (timestamp suffix); import never merges
  or overwrites. The import is checkpointed per phase onto an `Import` tracking record so a crashed import
  can be **resumed** or **discarded**, runs synchronously by default (no queue worker required) with an
  opt-in `run_in_background` toggle on the new `ImportSetting` singleton, and — like export — is behind
  the admin gate with resume/discard guarded by a per-owner `ImportPolicy`. See
  `documentation/architecture.md` → *Static site import*.
- New `x-delete-button` component: the labelled, full-form sibling of `x-icon-delete-button`
  (`<form>` + `@csrf` + `@method('DELETE')` + native `confirm()` dialog around a
  `x-button variant="danger" :icon="true"`). Replaces 9 hand-written `onsubmit="return confirm(...)"`
  delete forms at the bottom of entity edit pages (Act, Chapter, Plotline, Scene, Event, Project,
  Codex entry, Codex attribute, and the scene share-link "Revoke" action).

### Changed

- Migrated the 9 admin (`admin/settings`, `admin/database`, `admin/appearance`, `admin/data`) and
  profile (`profile/partials/*`) settings panels from the hand-rolled Breeze
  `p-4 sm:p-8 bg-white shadow sm:rounded-lg` panel to `x-card`, using its `header` slot for each
  panel's title + description. `profile/edit.blade.php` no longer wraps each `@include` in its own
  panel `<div>` — each partial now owns its `x-card` directly. Also resolved two more of the
  previously-unmigrated `x-heading` gaps: the profile/admin panel titles now use level 3, the
  `admin/data` Epub-export subsection heading uses level 4, and the public "share link expired" page's
  `<h1>` uses level 1.
- Migrated every call site off the legacy `x-primary-button` / `x-danger-button` / `x-secondary-button`
  Breeze components onto `x-button` (`variant="primary|secondary|danger"`), then deleted the three
  legacy components. Gave `x-button` an `icon` prop (leading floppy-disk icon for `primary`, trash for
  `danger`) to carry over the `:icon="true"` behaviour those components had gained. `x-button` defaults
  `type` to `submit` for every variant (the old `x-secondary-button` defaulted to `type="button"`), so
  every migrated secondary "Cancel"/"Copy"/"Regenerate"-style button that relied on that implicit
  default now sets `type="button"` explicitly to keep its original non-submitting behaviour.
- Migrated page-header and section titles across the app to the shared `x-heading` component instead
  of hand-styled `<h1>`–`<h6>` tags, for the instances whose existing classes matched one of the
  component's scale levels exactly. Redefined the scale's level 2 to `text-xl font-semibold
  text-gray-800` (the app's actual page-header size) and added a new level 6 for the smallest
  uppercase group labels; levels 3–5 shifted down accordingly. See `documentation/ui-components.md`
  for the full scale and why level 2 is pinned to the header-title size. A few headings with no exact
  match to any level (the profile/admin `text-lg font-medium text-gray-900` panel headings, the Story
  overview's act/chapter anchor headings, and a couple of one-off labels) were intentionally left
  unmigrated rather than force a visual change.
- Consolidated edit/delete/save/remove/close/download controls across the site onto a small set of
  shared button components instead of ad hoc text buttons. Row-level actions (list rows, the Codex
  attribute-timeline period editor) are small outline icon-only buttons; main entity actions (Save,
  Delete/Revoke on each entity's edit page) are full-size labelled buttons with a matching icon
  (`x-primary-button`/`x-danger-button` gained an `:icon` prop). The Codex attribute-timeline period
  "Remove" button now reuses `x-icon-delete-button` instead of a separate labelled danger button.
- Codex entry edit page: added a `border-t` divider and extra spacing above the Attribute timeline
  section so it reads as its own section rather than a continuation of the entry form.
- Codex entry create/edit form now uses a two-column layout (main content 9/12, a Cover-above-Tags
  sidebar 3/12) instead of three columns. Reference images and reference files moved out of the
  sidebar into a full-width tabbed block ("Reference images" / "Reference files") above the Save
  button. Reference image thumbnails are clickable and open in a lightbox; reference files show a
  "View" trigger that previews the file in a modal iframe alongside an explicit "Download" link.

### Added

- Installed `secondnetwork/blade-tabler-icons` (Composer, vendored SVGs — no CDN, works airgapped)
  and migrated every icon button on the site to it. New shared components `x-icon-save-button`
  (outline, for row-level saves), `x-icon-close-button` (outline, with a `light` variant for
  on-dark overlays), and `x-icon-download-button` (outline).
- Epub export from **Admin → Export & import → Export**, alongside (not replacing) the existing
  `.zip` export. A signed-in project owner picks a project and downloads a standard `.epub` file
  built by `App\Services\EpubExporter` via `rampmaster/phpepub`. Acts render as their own divider
  page ("Act N" + the act's name, blank names omitted, no description); Chapters start new pages
  ("Chapter N: title") with their Scenes' Markdown compiled to clean HTML and joined by `<hr>`, no
  per-scene titles or descriptions. Chapters with zero Scenes, and Acts left with zero surviving
  Chapters, are silently omitted from the book, TOC, and spine; a project with nothing left after
  that filtering fails with a clear error instead of downloading a broken file. Every export opens
  with a story title page (the Project's name, centered and set larger) followed by an in-book
  table of contents page — a real, readable spine page distinct from the reader's own EPUB 3 nav
  chrome — listing every surviving Act with its surviving Chapters nested underneath, each linking
  straight to its page; both front-matter pages precede the story itself in reading order. Act
  headings (the "Act N" number and the act's name) are likewise centered and set larger than body
  text. The table of contents (both the nav and the in-book page) is two-level (Acts nesting their
  Chapters). A dedicated, epub-only CommonMark pass
  converts `--`/`---` to en/em dashes, `...` to an ellipsis, and straight quotes to curly quotes —
  `Scene::renderedContents` (used by the Story overview, share page, and the existing `book/`
  export) is deliberately untouched. `Project` gained six new optional book-metadata fields
  (`language` — required, defaults `en`; `author`; `publisher`; `rights`; `isbn`, validated as a
  real ISBN-13 via the new `App\Rules\ValidIsbn`; and `cover_image`, uploaded via the Project edit
  form reusing `CodexMediaRules`' validation and the `public` disk), editable from the Project edit
  screen and mapped onto the epub's OPF metadata (Dublin Core fields, a generated
  `urn:imagoldfish:project:{id}` identifier plus a second `urn:isbn:` identifier when set, and
  EPUB accessibility metadata — `accessibilityFeature`/`accessMode`/`accessibilitySummary`). Every
  generated document declares `lang` from `Project.language`. Generated OPF documents are
  structurally validated against a RelaxNG schema vendored from the `epubcheck` project (converted
  from its `.rnc` sources at build time — no JVM at runtime); content/nav XHTML is validated for
  well-formedness. The export page links to the official
  [epubcheck](https://www.w3.org/publishing/epubcheck/) tool for authors who want full conformance
  verification. Authorization mirrors the existing export (`ProjectPolicy@view`, a foreign
  `project_id` 403s). See `.specs/shipped/2026-07/epub_export_v1/resolution-log.md` for the library
  research (the spec's originally-implied `grandt/phpepub` is dead since 2016) and several
  epub-library quirks worked around along the way.
- Portable toolchain & shell conventions in [`.claude/conventions/tooling.md`](.claude/conventions/tooling.md),
  referenced by a single pointer line in `CLAUDE.md`. The rules select the shell by *tool
  availability* (never by OS name — no shell is privileged), forbid carrying one shell's syntax
  into the other's tool (the platform-independent rule that prevents the cross-shell bug class),
  map lockfiles to package managers, and single-source canonical commands (test = `composer test`).
  This is Claude-workflow tooling only — no application code, routes, or runtime behavior changes.
  (A machine-local env cache + SessionStart hook were explored alongside these rules and then
  dropped as over-built for the payoff; see the feature's `resolution-log.md` and git history.)
- Project export to a downloadable `.zip` from **Admin → Export & import → Export**. A signed-in
  user picks one of their own projects, chooses whether to include images & files, and downloads an
  archive built by the HTTP-agnostic, async-ready `App\Services\StaticSiteExporter`. The archive is
  two-layered. The **`data/`** layer is a lossless, machine-readable copy (source of truth for a
  future import): the Story tree (project + acts → chapters → scenes), the Timeline (plotlines +
  events, including the seeded main plotline and Start/End bookends), and the Codex (entries with
  aliases/tags/attribute-values-over-time/media, plus flat attribute-definition and tag lists), every
  entity a `<id>-slug` directory of JSON + raw field files. Content fields are stored verbatim — never
  re-rendered or re-sanitized. The **`book/`** layer is the human reading version: a TOC `index.html`
  plus one compiled HTML page per chapter (scene `contents` rendered Markdown → HTML, joined by `<hr>`,
  with prev/next reading navigation crossing act boundaries) — the only place the export renders
  Markdown. A top-level **`README.md`** greets whoever opens the zip: project name, export date, the
  description as plain text, and a note pointing humans to `book/` and machines to `data/`. Media
  **bytes** are governed by the "Include images & files" toggle; media metadata is
  written regardless. Authorization walks `ProjectPolicy` on top of the admin gate, so a foreign
  `project_id` 403s rather than silently exporting another user's project. `ext-zip` is now a declared
  `composer.json` dependency. The export/import format contract lives in
  [`documentation/export-format.md`](documentation/export-format.md).
- Admin Configuration area (`/admin`): a settings hub with a left sidebar switching between four
  sections, every route behind `auth` plus a single `access-admin` Gate (returns true for any
  authenticated user — the deliberate continuation of the `CrawlerSetting` no-`is_admin` posture,
  encoded once on the route group so it can be tightened later without touching controllers). The
  user-dropdown entry now reads **"Configuration"** and lands on General settings. Sections:
  **General settings** (hosts the search-engine visibility form, see *Changed*), **Appearance &
  accessibility** (placeholder for future graphical/accessibility options), **Export & import** (an
  accessible inline-Alpine tab interface — WAI-ARIA `tablist`/`tab`/`tabpanel`, roving tabindex,
  arrow-key navigation — stubbed "coming soon"; the backup/restore engine is a separate future
  spec), and **Database configuration** (read-only display of the active connection — driver,
  database name/path, host; the password is whitelisted out in the controller and never reaches the
  view). Shared `<x-admin-layout>` + sidebar partial reuse the documented nav active-state pattern
  (`aria-current="page"`, never colour-only).
- Active-state highlighting for the **desktop** primary-nav dropdowns (Timeline, Codex, Story) and
  their collapsed trigger buttons: the item matching the current route now renders with the
  light-panel highlight (`bg-aqua-50 text-navy-900 font-semibold`) and carries `aria-current="page"`,
  and a trigger reflects when any of its child routes is active (`text-white border-flame-500`,
  matching the `x-nav-link` active look). Previously only the responsive (mobile) menu highlighted;
  the desktop dropdowns highlighted nothing.
- Friendly empty states on the index pages. The shared `x-table-empty` component now renders two
  distinct messages instead of a single bare "no results" row: a genuinely empty collection shows
  "No :items yet." with a primary button pointing at the create action, while a collection hidden by
  an active search/filter shows "No :items match your search or filters." (the toolbar's *Clear*
  link is the way back). Wired into the Codex (characters/locations/organizations) and the
  events/acts/chapters/scenes indexes.
- Dedicated feature tests for `ActController`, `ChapterController`, and `StoryController`
  (`ActTest`, `ChapterTest`, `StoryTest`), closing the last of the coverage gaps noted in
  `CLAUDE.md` (Scenes were covered earlier by `SceneTest`). Each covers the index, the full CRUD
  surface, project authorization (owner succeeds, non-owner gets 403 on read and every write path),
  validation failures, the auto-assigned `position` invariant, and the move-up/move-down sibling
  swap (including that it is scoped to the correct parent and is a no-op at the ends). `StoryTest`
  additionally asserts the read-only overview renders the nested act → chapter → scene tree in
  `position` order.
- Dedicated feature tests for `PlotlineController` and `EventController` (`PlotlineTest`,
  `EventTest`), previously only covered indirectly through `ProjectTest`. Each covers the full
  CRUD surface, project authorization (owner succeeds, non-owner gets 403 on read and every write
  path), and the domain invariants: the `is_main` plotline and the `is_fixed` Start/End bookend
  events are un-deletable (403), and `WithinEventWindow` is enforced on both the event store and
  update paths.

- Hidden from crawlers: a global toggle to hide the whole site from search engines, delivered as
  a dynamic `/robots.txt` plus a `noindex, nofollow` meta tag on every public-facing layout. The
  policy is one application-wide `CrawlerSetting` singleton (global — owned by no `Project`, read
  via `CrawlerSetting::current()`, lazily seeded from `config/crawlers.php`, default **hidden**).
  `RobotsTxtGenerator` builds robots.txt from the setting: when hidden it emits one allow-group
  per whitelisted crawler (a user-agent whitelist, one term per line, validated line-safe) then a
  catch-all `Disallow: /`; when off it allows everyone. The `x-robots-meta` component is the single
  source of the meta string, wired into `app`/`guest`/`welcome` (toggle-governed) and `public`
  (forced — shared scenes stay hidden regardless). An authenticated settings screen
  (`/settings/crawlers`, "Site settings" in the nav) edits the toggle and whitelist; it is the one
  deliberate departure from project-scoped authorization — any authenticated user may edit the
  global setting (no `is_admin` role).
- Scene sharing (foundation): two nullable columns on `scenes` — `share_token` (unique, stored
  raw) and `share_expires_at` — backing one revocable public share link per scene. `Scene` gains
  a `share_expires_at` datetime cast and two helpers: `isShared()` (token set **and** expiry in
  the future) and `shareUrl()` (public URL by route name, or null when unshared). Neither column
  is mass-assignable — the token is set explicitly in the controller. Share-link lifetimes come
  from a `config/sharing.php` whitelist (`scene_link_durations`: 24 hours / 7 days / 30 days) that
  the owner picks from, never a hard-coded literal. Controllers, routes, and views follow in later
  tasks.
- Scene sharing (public page): an unauthenticated, read-only view of a shared scene at
  `GET /shared/scenes/{token}` (`shared.scenes.show`), served by `SharedSceneController@show`
  **outside** the `auth` group — the opaque token is the only gate (no policy; documented as the
  single deliberate exception to "every action authorizes"). An unknown token returns 404 and an
  expired/revoked token renders a friendly branded 410 page (`shared/scenes/expired.blade.php`)
  rather than a bare error, checked via `Scene::isShared()` so a leaked-but-expired URL is inert.
  The page uses a dedicated no-nav `<x-public-layout>` whose `<head>` carries a
  `noindex, nofollow` robots meta, and renders only the scene title (Arabic `chapter.position`,
  em-dash), the description (collapsed card via `x-rich-text`) and the Markdown `contents` — the
  scene's `notes` are **never** exposed. The owner edit-page UI that generates these links follows
  in the next task.
- Scene sharing (owner management): `SceneShareController` with `store` (generate/rotate the link)
  and `destroy` (revoke it), exposed as authenticated `POST`/`DELETE /scenes/{scene}/share`
  (`scenes.share.store` / `scenes.share.destroy`). `StoreSceneShareRequest` validates the chosen
  duration against the `config('sharing.scene_link_durations')` whitelist via `Rule::in`, and both
  the request and controller authorize by walking up to the owning project (`ProjectPolicy@update`
  — non-owners get 403). The token is `Str::random(48)`, set explicitly (never mass-assigned);
  re-posting `store` rotates it and resets the expiry, invalidating the previous URL. The public
  view route and the edit-page UI that posts to these endpoints follow in later tasks.
- Scene sharing (owner UI): a "Share this scene" card on the scene edit page with two states driven
  by `Scene::isShared()`. Unshared shows a duration `<select>` (populated from
  `config('sharing.scene_link_durations')`, default preselected from `scene_link_default_duration`)
  and a "Generate share link" button posting to `scenes.share.store`, surfacing `duration`
  validation errors and preserving `old()`. Shared shows the public URL in a read-only field with an
  accessible Copy button (inline Alpine `navigator.clipboard.writeText` + "Copied!" confirmation),
  the expiry both absolute and relative, a Regenerate button (re-POST `store`) and a Revoke button
  (`DELETE scenes.share.destroy`). Reuses existing components only — no new component or route.
- Rich-text (WYSIWYG) editing for the app's free-text fields. A Tiptap-backed editor component
  (`x-wysiwyg`) with both an always-visible formatting toolbar **and a Notion-style `/` slash
  command menu** (headings, bold/italic/underline/strike, lists, blockquote, inline/block code,
  links, horizontal rule) replaces the plain `<textarea>` on every rich field, as **progressive
  enhancement** over a real textarea (a JS-off submit still works and `old()` repopulates on
  validation failure). Rich-HTML content is sanitized **server-side on write** by
  `App\Services\HtmlSanitizer` (HTMLPurifier, a strict allow-list centralized in
  `App\Support\RichTextFields`) via per-field set-mutators, so the DB never holds unsafe HTML; it
  is rendered back only through the `x-rich-text` component (`x-rich-text-excerpt` gives index
  tables an escaped, tag-stripped preview). **`Scene.contents` uses the same editor in Markdown
  mode** (`@tiptap/markdown`): it gains the WYSIWYG authoring experience while its stored value
  stays clean CommonMark (`ValidMarkdown` + `Str::markdown()` unchanged). The slash menu reuses
  `@tiptap/suggestion` + its bundled `@floating-ui/dom`, so it needs no extra dependency. Image
  upload is intentionally **not** in this version. Documented in
  [`documentation/rich-text.md`](documentation/rich-text.md).
- Codex: a project-scoped reference aggregate for the story's **characters, locations, and
  organizations**. All three share one `codex_entries` table keyed by a `CodexEntryType`
  enum and one `CodexEntryController`, with the kind carried as a `{type}` route segment
  (`characters`/`locations`/`organizations`). Each entry has **aliases**, flat **tags**
  (reusable per project), Markdown **descriptions**, and **media** (a single cover plus
  reference images/files on the `public` disk; cover is the `codex_media` row with
  `collection = Cover`, not a FK). **Temporal attributes** — attribute definitions
  (`codex_attributes`, with an `applies_to` array of entry types) whose values form a
  **start-anchored step function**: each period runs from its anchoring event until the next
  (or the *End* event), so the timeline stays gap-free and a value can be resolved "as of"
  any moment. The new `App\Services\AttributeTimeline` (the project's first `app/Services`
  class) owns resolution and gap-free upserts/removals; `App\Services\CodexMediaService`
  owns file storage, the single-cover rule, and on-disk cleanup. Scene and event pages gain
  **"as of" panels** showing each entry's attribute values at that moment (e.g. a scene during
  *Back to class* shows the character's hair as black). Authorization walks up to the owning
  `Project` (no new policies); a Codex nav dropdown sits between Timeline and Story.
  `MelusineSeeder` seeds a demo set (Mélusine with aliases/tags and a hair-color timeline,
  Raymondin, the Castle of Lusignan, and the House of Lusignan) by calling the timeline
  service directly, since seeding runs with model events disabled.
- Scene ↔ Event links, two relationships. **"Happens during"** — an optional
  `scenes.event_id` foreign key (`nullOnDelete`) placing a scene during a single event;
  chosen on the scene form via a select or an inline "New event" quick-create (auto-attached
  to the Main plotline). **"Mentions"** — an optional `event_scene` many-to-many pivot, edited
  as a checkbox list. Unassigned scenes (no "happens during" event) are flagged with a red
  border on the scenes index and Story overview. Deleting an event unassigns its scenes (via
  the FK) and drops its mention rows (pivot cascade); the event edit page lists the scenes
  happening during / mentioning it. The scene form's "mentions" input is a searchable,
  chip-based event picker (`x-event-picker`, client-side Alpine filter by name/date) rather
  than a checkbox list, so it scales to projects with many events.
- Bookend timeline events: every project is auto-created with two fixed events, "Start"
  (first day of year 0001) and "End" (first day of year 3000), attached to the main
  plotline. Both carry `events.is_fixed` and cannot be deleted (delete button hidden in
  the events index/edit views, `abort_if` guard in `EventController@destroy`), mirroring
  the un-deletable main plotline. `MelusineSeeder` creates them manually since seeding
  runs with model events disabled.
- Project theme palette registered in `tailwind.config.js`: `ocean` (#219EBC),
  `aqua` (#8ECAE6), `navy` (#023047), plus `sun` (#FFB703) / `flame` (#FB8500)
  accents, each as a full shade scale.
- `x-table` component family for the striped, sortable index tables (plotlines, events,
  acts, chapters, scenes): `x-table` (card + `<table>` + `head` slot), `x-table-heading`
  (non-sortable header cell), `x-table-row` (striped body row), and `x-table-empty`
  (no-results row). Documented in [`documentation/ui-components.md`](documentation/ui-components.md).
- Reusable UI component library (Blade components in `resources/views/components/`):
  `heading` (unified `<h1>`–`<h6>` scale), `button` (variant/size, renders `<a>` or
  `<button>`), `card`, `badge`, `alert` (dismissible, contextual variants),
  `breadcrumbs` (data-driven), `tooltip`, `popover`, and `dialog` (header/body/footer
  modal built on the existing `modal` shell). Documented in
  [`documentation/ui-components.md`](documentation/ui-components.md).
- Scene status workflow: scenes now carry a `status` (`Draft`, `To Proofread`,
  `To Edit`, `Final`) backed by the `SceneStatus` enum, plus a freeform `notes`
  field. Status renders through a reusable `scene-status-badge` Blade component on
  the scene create/edit/index screens and the story overview.
- Story Overview page (`projects.story.index`) combining the full act → chapter →
  scene tree on one read-only page, with a collapsible table of contents and scene
  contents rendered as Markdown.
- Feature tests for the scene resource (`tests/Feature/SceneTest.php`) covering CRUD,
  authorization, validation, and position auto-assignment; model factories for
  `Act`, `Chapter`, and `Scene`.
- Project coding guidelines (`.claude/guidelines.md`) and a `documentation/` folder
  (architecture, code style, best practices, glossary).
- Scene sharing (polish): the expired/revoked 410 page now shows a "This link expired X ago"
  relative-time hint (`share_expires_at->diffForHumans()`). The controller passes **only** the
  expiry timestamp — never scene content — and the hint is omitted when no expiry is recorded, so
  no data leaks. Covered by `SceneShareTest`.
- Promoted `league/commonmark` to a direct dependency in `composer.json`'s `require`. The Story
  overview renders `Scene.contents` via `Str::markdown()`, which relies on it; it was only present
  transitively, so a dependency prune could have silently broken Markdown rendering.

### Changed

- Extracted three misplaced/duplicated helpers to the home the architecture already implies (pure
  refactors, no behaviour change). (1) The move-up/move-down position-swap logic that was copied
  verbatim across the Act/Chapter/Scene controllers now lives once in the `HasSiblingPosition` model
  trait (each model declares its `siblingScopeColumn()`; the two-row swap runs in a transaction), and
  the controllers call `$model->moveUp()` / `moveDown()`. (2) The HTML-to-plain-text converter that
  had drifted into `StaticSiteExporter` moved to `App\Support\RichText::toPlainText()` beside the
  rest of the rich-text module. (3) The `Str::markdown($scene->contents ?? '')` render duplicated in
  three views (Story overview, public share view, book export) is now the single `Scene::renderedContents`
  accessor, so the null-guard and renderer choice have one home. Each moved helper gained a direct
  unit/feature test at its new home.

- The search-engine visibility ("hidden from crawlers") settings screen moved out of its standalone
  `/settings/crawlers` route into **Admin → General settings** (`/admin/settings`), under a "General
  settings" heading. The form, validation (`UpdateCrawlerSettingRequest`), and the `CrawlerSetting`
  singleton are unchanged — only the route (`crawler-settings.*` → `admin.settings.*`), the
  controller (`CrawlerSettingController` → `GeneralSettingsController`), and the wrapping layout
  changed. The old `/settings/crawlers` route was removed (no redirect alias); its behavioural tests
  were relocated into `AdminConfigurationTest`.

- The "no happens-during event" affordance on scenes is now explained: the red left border and the
  "Unassigned" badge on both the scenes index and the Story overview carry a `title` tooltip
  ("This scene has no “happens during” event yet."). Previously the red border's meaning was
  undocumented in the UI.

- The `x-dropdown-link` component now accepts an optional `active` prop (default `false`), mirroring
  `x-nav-link` / `x-responsive-nav-link`: pass `:active` to get the highlight plus `aria-current="page"`.
  Untouched call sites (the Settings dropdown) are unaffected. The nav's route-match expressions were
  consolidated into a single `@php` block at the top of the `navigation.blade.php` project guard — one
  source of truth reused by the desktop triggers, the desktop dropdown items, and the responsive menu
  (no `Nav` support class or view composer for a styling tweak).
- `ProjectTest` was trimmed to project-scoped concerns (dashboard, project CRUD/authorization,
  and the project-creation invariants that seed the main plotline and the Start/End bookends). Its
  plotline- and event-controller cases moved to the new dedicated `PlotlineTest` / `EventTest`, so
  each controller's coverage now lives in one place rather than being duplicated.
- Developer tooling: `.specs/` is now organised by lifecycle status — each feature folder lives
  under `.specs/<status>/<name>/` (`draft` / `expanded` / `planned` / `shipped`) and moves between
  those subfolders as it advances. The pipeline skills (`mp-spec-expander`, `plan-tasks`,
  `ship-plan`) and the `plan-implementer` agent locate a feature by name via the glob
  `.specs/*/<name>/`, and each stage now moves the folder in the same step it stamps the new
  `status:` frontmatter. `tests/Unit/SpecsStatusConsistencyTest` reconciles the two representations,
  failing `composer test` if a `spec.md` `status:` disagrees with its status folder (this caught the
  `hidden_from_crawlers` spec, which had shipped but was still stamped `planned`).
- Bookend **Start / End** event datetimes are now **editable** (previously frozen). In their
  place the bookends form a **containment window**: every non-fixed event must satisfy
  `Start ≤ event_datetime ≤ End` (inclusive), and a bookend edit may not swallow an existing
  event — Start can't move past the earliest regular event or reach End, and End is the mirror.
  A single rule (`App\Rules\WithinEventWindow`) enforces this on every event write path
  (`StoreEventRequest`, `UpdateEventRequest`, and the Scene inline `new_event_datetime`), and the
  datetime inputs carry `min`/`max` hints (`EventController::datetimeBounds()`,
  `Project::earliestRegularEvent()` / `latestRegularEvent()`). Because Start stays the earliest
  `is_fixed` event, the codex attribute-timeline baseline still resolves to the same row. The
  default Start moved from year 0000 to **year 0001** (Laravel's `date` rule floors at year 1 via
  `checkdate()`); End stays year 3000.
- Free-text description fields (`Project`, `Act`, `Chapter`, `Plotline`, `Event`, `Scene`,
  `CodexEntry`) and `Scene.notes` are now **rich HTML** rather than plain text — authored in the
  new WYSIWYG editor, sanitized on write, and rendered with formatting. The codex `description` in
  particular is **no longer Markdown**; it is now rich HTML like the others. **`Scene.contents` is
  unchanged** — it stays Markdown-only (`ValidMarkdown` + `Str::markdown()` on the Story overview),
  the one deliberate carve-out from the rich-text feature.
- Index tables (plotlines/events/acts/chapters/scenes) now render each row's
  description as small muted text beneath the title instead of a separate
  Description column, and the ordered lists (acts/chapters/scenes) expose their
  `#` position column as a sortable header.
- Index-row edit/delete icon buttons (`x-icon-edit-link`, `x-icon-delete-button`)
  are now outlined: transparent fill with a colored border and matching text —
  `navy-500` for edit, `red-600` (danger) for delete.
- Reskinned the app chrome to the theme palette: the Breeze default indigo accent
  (focus rings, links, active nav, `badge` primary/indigo variants) is now `ocean`;
  primary buttons fill with deep `navy` (higher contrast against their white label);
  and the active-navigation indicator uses the `flame` orange accent. Body text and
  semantic colors (info/success/warning/danger) are unchanged.
- Banded the app header: the top navigation bar is now `navy` (with its logo, links,
  dropdown triggers, and mobile menu lightened to `aqua`/white for contrast) and the
  page-heading bar below it is `ocean-800` (its heading text and back/edit links
  lightened to white/`aqua` via wrapper-scoped selectors in the app layout).
- Index table headers (`<thead>` on plotlines/events/acts/chapters/scenes, plus the
  `sortable-header` component) now use a `sun-400` background with `navy-900` cell
  text for strong contrast.
- Striped table rows are now `gray-100` (a step darker than the previous `gray-50`),
  set once in the shared `x-table-row` component.
- Reordered scenes in the story overview via AJAX (no full page reload).
- Restyled the story overview typography and act headings.
- Consolidated the `MelusineSeeder` chapters into fewer, denser chapters.
- Codex bookend events (Start/End) now have their `event_datetime` **frozen**: editing an
  `is_fixed` event can change its title/description/plotlines but not its date
  (`UpdateEventRequest` applies `prohibited`; the edit form hides the input). This keeps the
  Start/End sentinels that anchor the attribute timeline from being re-ordered.
- Removing an attribute period's Start baseline while later periods exist now returns a
  `403` (`abort_if`) instead of surfacing a `RuntimeException`.
- Codex route constraints, `CodexEntryType::fromRouteKey()`, and the navigation dropdown are
  all **derived from `CodexEntryType`** — no hardcoded `characters|locations|organizations`
  string lists remain, so adding a codex type no longer means editing five scattered lists.
- The codex navigation now highlights the **current** codex type (characters/locations/
  organizations) rather than always highlighting the first link.
- The codex index tag-filter dropdown hides tags that have no entries (`whereHas('entries')`).
- The attribute-definition form shows a hint that narrowing `applies_to` strands existing
  values for the removed entry types (non-destructive; they simply stop being shown).

### Fixed

- Moving a chapter to a different act via the edit form now works. The edit view offered an act
  selector and `ChapterController::update()` intended to honour it, but `act_id` is deliberately not
  in `Chapter::$fillable`, so the mass-assignment silently dropped it and the chapter never moved.
  Update now reparents through the `act()` relationship (keeping `act_id` guarded); regression
  covered by `ChapterTest::test_a_chapter_can_be_moved_to_another_act_in_the_same_project`.
- `php artisan db:seed` can now be re-run against a populated database: the admin
  user is only created when missing, instead of aborting on the `users.email`
  unique constraint before `MelusineSeeder` (already idempotent) was reached.
- The Home/Timeline/Story navigation menu no longer disappears on the event
  edit page: the layout's `$project` resolution chain now also resolves from the
  `event` route parameter (both the desktop bar and the responsive menu).
- The gap-free attribute-timeline invariant is now enforced on the period-store endpoint:
  `AttributeTimeline::upsertAt()` creates the Start baseline itself when a mid-timeline period
  is stored for a previously-unvalued (entry, attribute) pair, so `valueAt` stays total for
  `t ≥ Start` on every write path — not just entry creation.
- Codex media files no longer leak on disk when a **project** or **user account** is deleted:
  those deletions cascade at the database level and bypassed `CodexEntry`'s cleanup hook, so
  `Project` and `User` now have `deleting` hooks that purge the files (`purgeProject`) before
  the row cascade.
- Codex media disk I/O no longer runs inside the entry-save `DB::transaction`: file
  deletes/writes happen after commit, so a rolled-back save can no longer leave a media row
  pointing at a deleted file or orphan a written upload on disk.
- The attribute-timeline editor now renders validation errors (under `value` /
  `start_event_id`) and preserves typed input via `old()` on a failed save, which previously
  looked like Save silently doing nothing.
- Empty attribute values are now savable: `value` validates as `present`/`nullable` rather
  than `required`, so an empty baseline can be saved and a value can be cleared back to blank
  ("recorded as blank"), matching the create-form semantics.
- Codex media upload validation errors now render for **any** failing file index, not only the
  first (`reference_images.*` / `reference_files.*` instead of `.0`); the `x-input-error`
  component flattens the wildcard message bag.
- The codex entry form partial no longer runs a tag query at render time — the controller
  passes `projectTags`, keeping the query out of Blade.
