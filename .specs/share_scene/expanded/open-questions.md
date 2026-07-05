# Share scene — Open questions

Decisions to confirm before/while implementing. Defaults chosen in the other docs are noted.

## Resolved

- **Q1 — Title numbering:** ✅ **Arabic** `chapter.position` (`Chapter 1`), matching the rest of
  the app. The spec's "Chapter I" was informal. No Roman-numeral formatter.
- **Q5 — Storage model:** ✅ **Stored token** — nullable `share_token` / `share_expires_at`
  columns on `scenes`, one revocable link per scene (see `data-model.md`). No `scene_shares`
  table for this iteration.
- **Q6 — Signed URLs:** ✅ Rejected in favor of the stored token, because revocation is required.

## Still open

2. **What is "Title" in the title format?**
   `"Chapter I - Title: Scene title"` reads as `Chapter {n} - {chapter title}: {scene title}`.
   Confirm "Title" = the **chapter's** name (that is the assumed mapping).

3. **Separator style.** Spec uses `-`; the app uses an em-dash `—` between number and name
   (`Chapter :number — name`). *Default assumed:* follow the app's em-dash convention. Confirm.

4. **Configurable duration — global default or per-share choice?**
   "Valid for a configurable amount of time" is ambiguous. Two readings:
   - **(a)** A single global default in `config/sharing.php` (assumed default — simplest).
   - **(b)** The owner picks a duration when generating (24h / 7d / 30d). Needs a
     `StoreSceneShareRequest` with `Rule::in(...)` and a select on the edit page.
   Which one?

7. **Expired-link response.** 410 Gone with the default error page (assumed) vs a friendly
   branded "this link has expired" page on the public layout? Also: unknown token → 404 (assumed).

8. **Regenerate semantics.** Re-POSTing `scenes.share.store` **rotates** the token and resets
   expiry (assumed), invalidating any previously shared URL. Do we also want a separate "extend
   expiry without changing the URL" action?

9. **Token in the path vs query string.** `/shared/scenes/{token}` (path, assumed) vs
   `/shared/scenes?token=...`. Path is cleaner and the assumed choice.

10. **Which fields are public?** Assumed: **title, description (collapsed), contents**. Explicitly
    excluded: **notes**, status, event/plotline links, codex "as of" panel. Confirm nothing else
    (e.g. status badge, "happens during" event) should appear.

11. **Public layout.** New `layouts/public.blade.php` (`<x-public-layout>`) optimized for reading
    vs reusing the centered `layouts/guest.blade.php`. *Default assumed:* a dedicated slim public
    layout. Confirm.

12. **`noindex`.** Assumed we add `<meta name="robots" content="noindex, nofollow">`. Confirm
    the shared page should not be search-indexable.
