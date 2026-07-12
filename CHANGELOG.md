# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Entries are grouped under a release heading by change type (`Added` / `Changed` /
`Fixed` / `Removed`) and updated per feature or pull request — not per commit. The
per-commit "why" lives in each commit message body; richer rationale for a change
set belongs in its pull request description.

## [Unreleased]

### Changed

- Codex entry create/edit form now uses a two-column layout (main content 9/12, a Cover-above-Tags
  sidebar 3/12) instead of three columns. Reference images and reference files moved out of the
  sidebar into a full-width tabbed block ("Reference images" / "Reference files") above the Save
  button. Reference image thumbnails are clickable and open in a lightbox; reference files show a
  "View" trigger that previews the file in a modal iframe alongside an explicit "Download" link.

### Added

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
  `project_id` 403s). See `.specs/shipped/epub_export_v1/resolution-log.md` for the library
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
