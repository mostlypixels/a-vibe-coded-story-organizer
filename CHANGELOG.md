# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Entries are grouped under a release heading by change type (`Added` / `Changed` /
`Fixed` / `Removed`) and updated per feature or pull request — not per commit. The
per-commit "why" lives in each commit message body; richer rationale for a change
set belongs in its pull request description.

## [Unreleased]

### Added

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

### Changed

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
