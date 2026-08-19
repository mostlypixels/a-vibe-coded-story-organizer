---
paths:
  - "documentation/**"
  - ".specs/**"
  - ".claude/**"
---

<!-- Moved out of the root CLAUDE.md so it loads only when these paths are touched.
     It also governs commit bodies and PR descriptions, which are not files — the
     ship-pr skill points here for those. -->

### Documentation

Make the code, architecture, limits, and practices clear to junior developers.

* Code comments have their own rules: `.claude/rules/code-comments.md` (loads when you edit a
  PHP file).
* `documentation/README.md` is the entry point. It links to category indexes.
* Keep pages in the matching category:
    * `architecture/` for system structure and terms;
    * `features/` for application behavior;
    * `export-import/` for interchange contracts;
    * `interface/` for components and visual configuration;
    * `development/` for contributor workflows and standards.
* Each category has a `README.md`. Link every page from its category index and keep all pages
  reachable from `documentation/README.md`.
* In `documentation/`: explain *why*, not only *what*, and include examples for complex concepts.
  Use GFM alert callouts for emphasis, e.g. `> [!WARNING]` for pitfalls and `> [!NOTE]` for tips
  (these render in color on GitHub and in the IDE; inline HTML `style=` is stripped by GitHub, so
  prefer callouts).
* Update documentation whenever architecture or workflows change; keep it synchronized with the code.

#### Verbosity

Long documents fail twice over: nobody reads them, and they burn context when an agent loads
them. Both readers want the same thing — the facts, findable, with nothing between them.

* **Lists by default; prose only to explain *why*.** Facts, rules, options and steps are
  bullets or table rows. A sentence or two of prose is right where the reasoning genuinely
  needs it. Never a paragraph that restates the list next to it.
* **Don't restate the code in English.** A doc earns its place with what the code can't say:
  invariants, pitfalls, why-not-the-obvious-thing, cross-cutting rules. Name the class and
  move on; the reader can open it.
* **No padding.** No "Note that…", no recap paragraph under a heading, no summary that
  repeats the section above it, no preamble announcing what the section will cover.
* **Keep entry points short.** The root index leads to category indexes. Architecture names the
  load-bearing rules and links to feature guides for detail. Do not copy the detail into indexes.
* **Use relative links.** GitHub navigation must work from the file's current folder. Run the
  documentation link test after moving or renaming a page.
* **If a doc needs a "skim this" instruction, it is too long.** Cut it; don't annotate it.

This applies to everything written here: `documentation/`, specs, `.claude/` skills and
agents, commit bodies, PR descriptions.
