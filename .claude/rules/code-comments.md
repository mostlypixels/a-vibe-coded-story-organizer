---
paths:
  - "**/*.php"
---

<!-- Split out of .claude/rules/documentation.md, whose paths are documentation/**, .specs/**
     and .claude/** — so the rule governing code comments never loaded when a PHP file was
     edited. It reaches the author now. Prose rules for docs and specs stay in that file. -->

### Code comments

Comment the *why*, never the *what*. A file or class docblock says why the thing exists and what
bites; inline comments appear only where the reason isn't already in the code. Self-explanatory
code gets no comment. Long-form rationale belongs in `documentation/`, linked — not inlined.

Don't shorten an existing long comment without asking.

#### Never cite a temporary file

Handoffs (`.specs/**/handoff.md`) and plan task files are scratch. A handoff is deleted when its
feature is done; a task file moves to `plan/implemented/`. A comment pointing at either one rots
the moment the file moves.

* No `handoff.md`, no `task 04`, no `plan/NN-<slug>.md`.
* **State the rule instead of citing where it was decided.** If a comment needs the spec to make
  sense, the comment is incomplete.
* `expanded/*.md` and `spec.md` are citable — they survive into `.specs/shipped/`.

#### Write in Simplified Technical English (ASD-STE100)

This code is read by junior developers. STE is built for exactly that reader, and it gives a
rewrite an objective target instead of "make it shorter".

* Sentences of 20 words or fewer. One topic each.
* Active voice. Present tense.
* One word, one meaning. Keep the articles (`the`, `a`).
* No `-ing` verb forms. Keep noun clusters under four words.

Do not chase the full ASD-STE100 approved-word list — the rules above carry most of the benefit.

**Before** — one sentence, 42 words, three topics, and a build history no reader needs:

```php
 * Task 11 replaced that hard-coded title → toc → body sequence with addSections(): an ordered
 * walk over PublicationSetting::section_order that also renders the front/back-matter Markdown
 * pages (dedication/acknowledgements/preface/postface) at whatever position the author placed
 * them.
```

**After** — two sentences, present tense, active, no task number:

```php
 * {@see addSections()} follows `PublicationSetting::section_order`. The author sets the
 * position of the title page, the table of contents, the body, and the Markdown pages
 * (dedication, acknowledgements, preface, postface).
```

`app/Services/EpubExporter.php`'s class docblock is the full worked example.

#### Shape of a rewritten docblock

*What it does now → the rules that bite → the warning.* Never *how it came to be* — git holds
that. Put a pitfall in a `> [!WARNING]` callout so it survives a skim.
