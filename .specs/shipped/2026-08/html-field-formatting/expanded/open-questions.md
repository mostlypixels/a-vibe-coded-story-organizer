# Open questions

1. **Palette size and source.** Six named hues drawn from the darker `PlotlineColors`
   shades — red, orange, green, blue, purple, grey. Enough to distinguish factions or
   speakers, small enough to stay contrast-checked. *Recommend: six.*
2. **Justify.** Include it. It is block alignment like the others, costs one array entry,
   and codex prose is exactly where an author wants it. *Recommend: left/centre/right/justify.*
3. **One sanitizer profile or several.** Two: `Rich` and `Structural`. Per-field profiles
   would need a field→profile map with no caller asking for one yet. *Recommend: two.*
4. **Do codex fields already reach appendices unchanged?** Yes —
   `EpubExporter` line ~492 passes `RichText::toXhtmlFragment($entry->description)` into
   `exports/epub/appendix-entry.blade.php`. Only the stylesheet needs the new rules.
5. **Does `Scene::renderedContents` strip raw HTML today?** It did not. **Fixed and
   shipped in #117** before this feature. `Str::markdown()` ran with CommonMark's default
   `html_input: allow`, and `ValidMarkdown` rejects nothing, so raw HTML reached the story
   view, the unauthenticated share route, the static site, and the EPUB — a stored-XSS
   vector, not just a formatting leak. `AuthorMarkdown::render()` now sanitizes every
   author-Markdown render. *No open decision left; the profile split in `architecture.md`
   hangs off that method.*

6. **Are colour and alignment changes worth diffing?** `Diff\HtmlTokenizer` reads only
   `data-callout-type` and task-list attributes, so a colour-only edit would show as no
   change while still creating a revision. *Recommend: out of scope, and say so in
   `documentation/features/revisions.md` rather than leaving it undiscovered.*
7. **Theme presets versus a fixed palette.** A fixed palette with a `prefers-color-scheme`
   dark variant, not eleven per-preset token sets. *Recommend: fixed, and revisit only if a
   preset proves unreadable.*
8. **Class prefix.** `rt-` for rich text. Alternative `wysiwyg-` collides with the
   editor-chrome classes already in `app.css` (`.wysiwyg-slash`). *Recommend: `rt-`.*
