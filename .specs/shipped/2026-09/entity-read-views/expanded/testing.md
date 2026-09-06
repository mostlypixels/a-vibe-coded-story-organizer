# Testing

Add to the existing files: `tests/Feature/{PlotlineTest,ActTest,ChapterTest,SceneTest,EventTest}.php`.
Mirror the `test_show_page_*` block in `tests/Feature/CodexEntryTest.php` (lines 575-710).

Per entity:

- `test_show_page_renders_<the fields>` — name/title, description, and the belongs-to line.
- `test_show_page_lists_its_<children>_in_order` — assert order, not just presence.
- `test_show_page_omits_a_section_with_no_content` — no empty card, no stray heading.
- `test_show_page_has_no_form_input_or_autosave_field` — copy the assertion at
  `CodexEntryTest.php:646`.
- `test_a_non_owner_cannot_view_the_show_page` — 403.
- `test_the_index_links_the_name_to_the_show_page_and_keeps_the_edit_icon` — copy
  `CodexEntryTest.php:46`.

Specific cases:

- Scene: prose renders (`renderedContents`), referenced codex entries appear, and an
  unassigned scene (no `event_id`) still renders.
- Event: `EventLifespanEntries` returns an entry under `inceptions` when it is the entry's
  `inception_event_id`, and the same event both starting one entry and ending another
  puts each in its own group.
- Act and chapter: the story number comes from `StoryNumbering::forBook()` and counts from
  the whole book, not the page.
- Plotline: the main plotline renders its badge and offers no delete.

Search: extend the `SearchDomain` unit coverage so `viewRoute()` returns a distinct
`show` route for all eight cases and no case falls back to `editRoute()`.

Query count: assert no N+1 on the act, chapter and event pages — the child lists walk
`chapter.act` and are the reason for the eager loads in `architecture.md`.
