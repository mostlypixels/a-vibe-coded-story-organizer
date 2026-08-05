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

The code must be understandable by junior developers — the code, the architecture, the pitfalls to
avoid, and the best practices to follow.

* Comment the *why*, never the *what*. A file or class docblock says why the thing exists and what
  bites; inline comments stay ≤30 words and appear only where the reason isn't already in the code.
  Self-explanatory code gets no comment. Long-form rationale belongs in `documentation/`, linked —
  not inlined. Don't shorten existing long comments without asking.
* Maintain a `documentation/` folder of **GitHub-flavored Markdown** files, at least:
    * `best-practices.md`
    * `code-style.md`
    * `architecture.md`
    * `glossary.md` — higher-level concepts and design patterns
    * add pages as needed.
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
* **Entry point short, deep dive linked.** A feature gets a compact section in
  `architecture.md` — what it is, the load-bearing pieces, the rules that bite — linking to
  `documentation/<feature>.md` for the full reference (see `revisions.md`). The deep dive
  holds detail, not padding: the same rules apply to it.
* **If a doc needs a "skim this" instruction, it is too long.** Cut it; don't annotate it.

This applies to everything written here: `documentation/`, specs, `.claude/` skills and
agents, commit bodies, PR descriptions.
