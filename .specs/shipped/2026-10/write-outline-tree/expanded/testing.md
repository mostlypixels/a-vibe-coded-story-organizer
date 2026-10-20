# Testing

## `tests/Feature/EditorTest.php`

- The outline lists every scene of the book in story order across acts and chapters (reuse the fixture of `test_previous_and_next_cross_chapter_and_act_boundaries`).
- Act and chapter labels match `StoryNumbering`, with continuous chapter numbers across acts.
- Only the current chapter's `<details>` is `open`.
- The current scene row has `aria-current="page"`; no other outline row has it.
- Scenes of another book of the same project are absent.
- An empty chapter shows "No scenes yet."
- The recent section renders open or closed from the stored `sections.recent`, and from the default when the key is absent.
- Query count: page load with 3 scenes equals page load with 30 scenes (pattern of `test_the_card_query_count_does_not_grow_with_the_cards`).
- The breadcrumb and the New scene dialog chapter list are unchanged after the switch to `EditorOutline`.
- Non-owner 403 is covered already by `test_a_non_owner_gets_403_on_both_routes`.

## `tests/Feature/EditorLayoutPreferenceTest.php`

- A payload without `sections.recent` fails validation.
- Update existing valid payloads to include `recent`.

## Unit

- `EditorOutline`: two siblings with the same `position` sort by `id`.

## JavaScript (`search.test.js`)

- A non-empty query hides the browse view (recent and outline); clearing it shows them again.
