# WYSIWYG — resolution log

The running record of **feedback/decisions**, **deviations** from the spec/plan, and
**issues → resolutions** hit while implementing and verifying this feature. This is the
canonical "what actually happened" companion to the forward-looking spec docs — read it before
extending the feature so you don't re-discover a resolved trap. (This file is the per-feature
convention: `.specs/<feature>/resolution-log.md`; see `plan-implementer` / `ship-plan`.)

## Feedback & decisions (from the user, during planning/implementation)

- **Image upload → deferred to v2.** No upload endpoint, no `project_media` table, no image slash
  command in v1. (Captured as the v2 seed spec `.specs/wysiwig-v2.md`.)
- **`Scene.notes` → rich HTML** (consistency with the other descriptions).
- **Editor library: spike then adopt.** Redactix was the initial favourite but unsuitable; landed
  on **Tiptap** (StarterKit v3). One editor framework only (anti-bloat).
- **Formatting UI: keep the toolbar *and* add the `/` slash menu** — both, Notion-style.
- **`Scene.contents`: a WYSIWYG that saves Markdown.** "Use Tiptap unless it can't emit clean
  Markdown, then Milkdown." Tiptap *can* (first-party `@tiptap/markdown`), so no Milkdown — the
  field is a `markdown`-mode `x-wysiwyg`; its stored value stays clean CommonMark.

## Deviations from the original spec/plan

- **v1 shipped a toolbar only, not the "notion like" slash menu the spec asked for.** The plan
  task assumed a slash popup needed an uninstalled positioning library, so it fell back to a
  toolbar. A follow-up added the real slash menu with **zero new deps** — `@tiptap/suggestion`
  (already installed) plus its bundled `@floating-ui/dom` for positioning. *Lesson: when the spec
  names a UX explicitly, don't quietly substitute a lesser one — check the real dependency cost
  first (it was free here).*
- **`Scene.contents` was left a plain Markdown `<textarea>` in v1**, then changed to a
  `markdown`-mode WYSIWYG. Only the *editing UI* changed; storage stays Markdown (`ValidMarkdown`
  + `Str::markdown()` unchanged).

## Issues → resolutions

1. **Rich text rendered unstyled ("styles not applied").** `@tailwindcss/typography` was never
   installed/registered, so the `prose` classes emitted **no CSS** and Tailwind's preflight
   stripped all heading/list/blockquote styling — formatting applied but looked identical to
   plain text. **Fix:** install + register the plugin in `tailwind.config.js`, and add
   `resources/js/**/*.js` to the `content` scan (the editor sets its `prose` classes from JS).

2. **Styling *still* looked broken after fix #1.** A stale **`public/hot`** file routed `@vite`
   to a **dead Vite dev server** (`:5173`), bypassing the production build entirely — so the
   rebuilt CSS never reached the browser. **Fix:** stop the dev server / remove `public/hot` so
   `@vite` serves `public/build`. *Diagnostic: check whether `public/hot` exists and whether the
   served HTML's asset URLs point at `:5173` vs `/build/assets/…`.*

3. **Toolbar buttons did nothing, while the slash menu worked.** The Tiptap `Editor` was stored as
   a **reactive Alpine property** (`this.editor`); Alpine wraps reactive state in a
   `@vue/reactivity` **Proxy**, and ProseMirror's view/state do not survive being proxied, so
   `this.editor.chain()…run()` silently no-op'd. The slash menu worked only because
   `@tiptap/suggestion` hands its commands the **raw** editor. **Fix:** keep the `Editor` in a
   **non-reactive closure variable**; only `ready`/`tick` stay reactive. *Reusable gotcha: never
   put a Tiptap/ProseMirror (or similar stateful third-party) instance in Alpine reactive data.*

4. **`Uncaught ReferenceError: editor is not defined` — editor failed to mount.** A broad
   find/replace of `this.editor` → `editor` (for fix #3) also clobbered the **legitimate**
   `this.editor` inside the slash extension's `addProseMirrorPlugins()`, where `this` is the
   *extension* instance, not the Alpine component — leaving a bare, undefined `editor`. **Fix:**
   restore `this.editor` there. *Lesson: a repo-wide `replace_all` is unsafe across scopes where
   the same token means different things (`this` differs between an Alpine component method and a
   Tiptap extension method) — scope the replacement or re-audit every hit.*

## Verification notes

- **`composer test` passing did not catch issues 1–4** — all four were client-side/asset/JS
  failures invisible to PHPUnit. **A feature with a JS/rendered surface is not "done" on a green
  test suite.** Verify by: `npm run build`; confirm the served HTML references `/build/assets/…`
  (not a dead `:5173` dev server); and **drive the editor in a real browser with the console
  open** (mount without error, toolbar toggles + highlights, `/` menu opens and applies, the
  Markdown field round-trips and hides Underline/Strike). If no browser automation is available
  in-session, hand the user the exact click-path to confirm rather than declaring success.
