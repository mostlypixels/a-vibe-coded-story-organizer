# Overview

## Problem

`x-wysiwyg` builds one toolbar from `App\Support\WysiwygToolbar`, gated by a single
`$markdown` flag that today removes only merge/split cells. Adding alignment and colour
without a second gate axis would put decoration into `Scene.contents`, which becomes EPUB
body text and is read by TTS.

## Goals

| Goal | Test |
| --- | --- |
| HTML fields gain block alignment and inline text colour | Toolbar cluster renders for `format=html` |
| Markdown fields gain nothing | `WysiwygToolbar(markdown: true)` returns no align/colour items; slash items match |
| Divergence stays in data | One `WysiwygToolbar`, one `buildExtensions()`, one toolbar Blade |
| Values are closed sets | Sanitizer strips any class outside the registry |
| Decoration reaches codex appendices | EPUB stylesheet carries the classes |

## Non-goals

Second toolbar component; inline `style`; font family/size; background/highlight colour;
alignment or colour on `Scene.contents`; per-user palette editing.

## User stories

- Author centres a codex entry's epigraph and colours a faction name; both survive export.
- Author opens a scene and sees no align or colour control at all.
- Reader with a custom EPUB stylesheet overrides `.rt-color-*` and loses nothing.

## Acceptance criteria

1. HTML field: align left/centre/right on paragraph and heading; colour on a selection.
2. Markdown field: neither control exists in the toolbar nor the slash menu.
3. Persisted HTML holds only `class="rt-align-*"` / `class="rt-color-*"`; anything else is stripped on write.
4. A hand-crafted `<span style="color:red">` posted to a rich field is stripped.
5. A hand-crafted `<span class="rt-color-red">` inside `Scene.contents` Markdown is stripped by `AuthorMarkdown::render()`, and rejected on import.
6. Codex appendix pages in the EPUB render both classes.
7. Every palette entry meets `ColorContrast::TEXT_FLOOR` against the light and dark reading surfaces.
