# Testing

## Feature: `tests/Feature/EditorCardTest.php`

- Owner gets the notes partial. A note linked after the page load appears, collapsed.
- A note with a `closed` row does not appear.
- Owner gets the codex partial, with attribute values as of the scene's event. An attribute set after the page load shows its new value.
- A non-owner gets 403, for both types.
- A type other than `codex` or `note` gets 404.
- The partial matches the page load: the cards of `scenes.write` and of the endpoint are the same HTML for the same data.

## Feature: `tests/Feature/EditorTest.php`

- The page-load query-count test still passes after the `EditorCards` split.
- Add the same query-count test for each section of the endpoint (1 card and 10 cards give the same count).

## JS: `resources/js/editor/cards.test.js`

- `focus` and `visibilitychange` to `visible` fire one GET each section, together only one.
- `visibilitychange` to `hidden` does nothing.
- A closed `<details>` makes no GET. Opening it makes one.
- The icon makes one GET.
- An equal response leaves the same DOM nodes in place (`toBe` on the old card element).
- A different response replaces the cards, and focus goes back to the same card button.
- A refresh waits for a running save.
- A toggle during the GET drops the response.
- A failed GET keeps the cards and sets `loadError`.
- `destroy()` removes the listeners.
