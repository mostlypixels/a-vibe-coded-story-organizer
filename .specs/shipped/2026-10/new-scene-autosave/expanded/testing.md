# Testing

In `tests/Feature/SceneTest.php`:

- Change the existing create test: assert the redirect goes to `scenes.write` for the new scene.
- New: the create page has no `name="contents"` and no `name="description"` input.
- New: after create, `GET scenes.write` for that scene returns 200 for the owner.

Kept as is: non-owner 403, chapter from another book, required name, position order.

Browser check (Playwright, `run-imagoldfish`): New scene → title → Create → lands in Write, caret in
the body, typed text autosaves.
