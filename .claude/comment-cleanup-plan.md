> [!NOTE]
> **Scratch file.** Delete it when this cleanup is done. Never cite it from code or from
> `documentation/` — it exists to remove exactly that kind of reference, and must not become the
> next one. Source of truth while the work is in flight; nothing more.

# PHP comment cleanup: ephemeral references, then over-long blocks

## Context

Two related problems in PHP comments, found while checking whether this project's comment volume
conflicts with the global concision rules.

**1. Comments cite files designed to be deleted.** Handoff files (`.specs/**/handoff.md`) are
temporary working files for resuming agent tasks, deleted once a feature is done. Plan task files
are the same kind of artifact, moving to `plan/implemented/` as they land. Neither belongs in a
code comment — but **324 citations across 118 PHP files** point at them (32 handoff, 292 task
numbers), and they are already decaying:

- `WordCountTest.php` cites `plan/04-scene-saving-hook.md`, which has moved to `plan/implemented/`.
- `RevisionOrigin.php` cites the literal elided string `.specs/.../handoff.md`.
- `task 13` never names *which* feature's plan folder — unresolvable even in principle.
- `StoryController.php` reaches into another feature entirely: "word-count spec, task 8".

**2. `app/` is 37% comment lines** (7,900 of 21,382) — but most of it is earned. See below.

Outcome: comments that stand on their own, in one consistent style, that stop rotting when a temp
file is deleted.

> [!NOTE]
> Counts taken 2026-08-05 after breadcrumbs merged (`b2f1b73`, #80). **Line numbers are already
> stale** — that PR moved `RevisionController`'s handoff sites from 24/32/348 to 27/35/373. Locate
> every site by content, never by a line number quoted here.

## The central finding: most of the 37% is earned

The largest comment blocks carrying no ephemeral reference are not padding:

- `AccentFolder` — why a hand-rolled map beats DB collation or `Str::ascii()` (SQLite folds ASCII
  case only; a nested-REPLACE chain overflows its parser), plus the 1-char→1-char invariant that
  lets `SearchSnippet` slice the *original* text at *folded* offsets.
- `RevisionReverter::restore()` — lock ordering, re-validation against today's rules, why the value
  is read back after `save()` rather than from `fresh()`, why the two writes are one transaction.

Trimming these mechanically would destroy real invariants. **No large mechanical win exists beyond
the ephemeral references.** The obvious candidate was checked and rejected:

| Candidate | Verdict |
|---|---|
| Tag-only docblocks (`@param`/`@return` only) | 346 of 393 tag lines carry generics or array shapes a typed signature cannot express — needed for static analysis. The other 47 carry prose descriptions. **Dead end.** |

Targeted, not volumetric. No density target.

## Style: ASD-STE100 Simplified Technical English

Every comment a step rewrites comes back in STE, and STE becomes the norm for code comments from
here on. It fits the audience — this code is read by junior developers — and gives a rewrite an
objective target instead of "make it shorter".

| In scope | Out of scope |
|---|---|
| PHP comments these steps rewrite | `documentation/` — **not rewritten this run** |
| All new PHP comments | `.specs/`, CHANGELOG, commit bodies, PR text |
| | Comments no step opens — they convert when next edited |

Mixed style is expected for a while. Every edit moves one direction; no separate retrofit project.

---

# Stage A — Rules first

Nothing is deleted in this stage. It exists so every later step is measured against a written rule,
and so work landing during the cleanup stops adding to the problem.

### A1. Create `.claude/rules/code-comments.md`

New file, scoped to PHP:

```yaml
---
paths:
  - "**/*.php"
---
```

**This fixes a live defect.** The comment rule currently sits in `.claude/rules/documentation.md`,
whose `paths:` are `documentation/**`, `.specs/**`, `.claude/**` — so **the rule governing code
comments never loads when a PHP file is edited.**

Contents:
1. The existing "Comment the *why*, never the *what*" bullet, **moved** out of `documentation.md`
   (delete it there; do not leave a copy).
2. The STE rules that bite — sentences ≤20 words, one topic each; active voice; present tense; one
   word one meaning; keep articles; no `-ing` verb forms; noun clusters under four words.
3. Handoffs and plan task files are scratch. Never cite them from code.
4. The rewritten `EpubExporter` docblock as the worked example.

Do **not** reproduce STE's ~900-word approved vocabulary — it will not be maintained. A rule with a
sample in the repo survives; a rule citing an external standard does not.

### A2. Add the pointer and the lifecycle line

- `CLAUDE.md` — one-line pointer, matching how `changelog.md` and `documentation.md` are referenced.
- `.specs/README.md` — one line in the lifecycle section: handoffs and plan task files are scratch,
  deleted or moved when the feature is done, never cited from code or permanent docs.

### A3. Capture the one at-risk rationale

`CodexAttributeValue.value` is deliberately **absent** from `AutosavableFields::REGISTRY`, because
`AttributeTimeline`'s time-travel semantics conflict with autosave. That reasoning exists only in
handoff §7, and an absence cannot document itself. Add a note near `REGISTRY` in
`app/Support/AutosavableFields.php`, under 30 words, in STE.

Do this **before** anything is deleted.

*Checked and not at risk: §8's import rules are stated in full in `ProjectGraphImporter`; §2.5's
coarse-trigger rule in `SceneContentsChanged`; §4.2/§4.3/§5.2/§9.2 in `documentation/revisions.md`.*

**→ Verify Stage A** (see Verification below), plus: open any PHP file and confirm the new rule
loads.

---

# Stage B — Handoff citations (32 sites, 22 files)

Near-mechanical. An audit classified **31 of 32 as self-sufficient** — the convention is to state
the rule inline and cite the handoff only as a source credit. Delete the citation, clean up orphaned
punctuation, keep the sentence. Rewrite in STE only where the sentence must change anyway.

```php
// before
 * (handoff.md §4.2): it must delete an "automatic", unlabeled row once it
 * is older than the retention window, *unless* that row is the newest ...

// after
 * It must delete an "automatic", unlabeled row once it is older than the
 * retention window, *unless* that row is the newest ...
```

Two shapes need care:
- Citation inside a larger parenthetical alongside a surviving reference — e.g.
  `(expanded/ui.md "History page", handoff.md §5.1/§5.3)` — drop only the handoff clause.
  `expanded/` refs stay; they survive into `.specs/shipped/`.
- `app/Enums/RevisionOrigin.php` — delete the whole trailing sentence `See .specs/.../handoff.md
  §4.2.`; the two sentences before it already state the rule.

| Step | Scope | Sites |
|---|---|---|
| **B1** | `app/Services/**`, `app/Models/**` | ~9 |
| **B2** | `app/Http/**`, `app/Enums/**`, `app/Support/**` | ~14 |
| **B3** | `tests/**`, `database/**`, `config/**`, `routes/**` | ~9 |

**→ Verify after each step.**

---

# Stage C — Task numbers (292 sites, ~100 files)

### C1–C2. Inline strips

`(task 04)`, `(task 9) — never a ...` drop out like Stage B. Two shapes need more:

- **Future-tense claims.** `tests/Unit/BreadcrumbsTest.php` and
  `tests/Feature/BreadcrumbsComponentTest.php` say a later task "wires this into the layout". It
  already happened. Cut the claim, not just the number.
- **Cross-feature references.** `StoryController.php` cites "word-count spec, task 8" for a rule
  about not calling `wordCount()` in the view. Restate the rule; the pointer is useless.

| Step | Scope |
|---|---|
| **C1** | `tests/**` — the bulk, mostly one-line asides where the test name already says what it covers |
| **C2** | `app/**`, `database/**`, `config/**`, `routes/**` |

### C3–C5. Docblock rewrites

Where task numbers carry the structure, removing them means restating the block in STE. Shape each
as *what it does now → the rules that bite → the warning*. One file per step — these need judgment,
not throughput.

| Step | File | Now |
|---|---|---|
| **C3** | `app/Services/EpubExporter.php` | 584 comment lines, 4,949 words, 31 refs. **Pilot — draft already written and reviewed.** |
| **C4** | `app/Services/Import/ProjectGraphImporter.php` | 332 lines, 2,341 words, 15 refs |
| **C5** | `app/Services/StaticSiteExporter.php` + `tests/Unit/Services/EpubExporterTest.php` | 583 lines combined, 35 refs |

**→ Verify after each step.**

---

# Stage D — Over-long ref-free blocks (~60 blocks)

Judgment, no mechanical component. `app/` holds 436 comment blocks of ≥6 lines with no ephemeral
reference; only the top slice is worth review.

| Step | Size | Blocks |
|---|---|---|
| **D1** | 200+ words | 9 |
| **D2** | 120–199 words | 51 |
| — | 60–119 words | 158 — leave alone |
| — | <60 words | 218 — leave alone |

**Cut:** narration of how the code got here; restatement of what the next line plainly does;
rationale already in `documentation/`, replaced by a link.
**Keep:** invariants, pitfalls, why-not-the-obvious-thing, anything documenting a fixed bug.

Preserve in meaning, in this stage and every rewrite above:
- `Revision::prunable()`'s `MAX(id)` warning ("do not simplify it back")
- `ProjectGraphImporter`'s replay-order `[!IMPORTANT]`
- `AccentFolder`'s 1-char→1-char offset invariant
- `EpubExporter`'s two isolation rules (own CommonMark converter; Blade-only HTML)

Expect a modest reduction. **If it starts to feel like bulk deletion, stop** — the rubric is the
point, not the word count.

**→ Verify after each step.**

---

# Verification

Two layers. Both run after **every** step above, not once at the end.

### Layer 1 — mechanical (I run these)

```bash
composer test                 # green; a failure means a @param/@return/@see was damaged
composer lint -- --test       # Pint clean; catches malformed docblocks, stray punctuation
git diff --stat               # comment lines only — any non-comment change is a mistake
git diff -- documentation/    # must be empty for every step after A2
```

### Layer 2 — a fresh verification agent (required)

**A separate agent verifies every rewrite.** It must start cold, with no context from the step that
produced the change — it cannot inherit my assumptions about what a comment "obviously still says".
I do not self-certify a rewrite.

Give the agent only: the step's changed files, and the git ref to diff against. Ask it to report
per file, `PASS` or `FAIL` with specifics:

1. **Information preservation** — for each changed comment, does every rule, warning, invariant and
   caveat in the OLD version still appear in the NEW one? List anything dropped or weakened. *This
   is the check that matters; the rest are cheap.*
2. **STE compliance** — sentences ≤20 words, active voice, present tense, no `-ing` verb forms,
   articles kept, noun clusters under four words.
3. **Comments only** — the diff touches no executable line.
4. **No stragglers** — no `handoff.md` or `task NN` reference remains in the touched files.

A `FAIL` on check 1 is blocking: restore the dropped fact before the step is considered done. Checks
2–4 are fix-then-continue.

### Final, after all stages

```bash
grep -rIn 'handoff\.md' app tests database config routes --include='*.php'          # expect none
grep -rInE '\b[Tt]ask [0-9]{1,2}\b' app tests database config routes --include='*.php'  # expect none
```

Review any straggler by hand — legitimate prose uses of "task" should not match the digit
requirement, but confirm rather than assume. Then spot-read `Revision::prunable()`,
`ProjectGraphImporter::importRevisions()`, `AccentFolder` and `EpubExporter` to confirm the preserved
warnings read correctly standalone.

---

# Out of scope

- **`documentation/`** — 3,900 lines across 15 files keep their current voice. Whether STE should
  govern docs is a later decision, once it has proven itself in comments. The only doc edits here
  are A1 and A2.
- **`expanded/*.md`, `plan/00-overview.md`, `resolution-log.md` citations** (~28) — these survive
  into `.specs/shipped/`. Revisit separately if they also churn.
- **Spec-lifecycle tooling** (`SpecsStatusConsistencyTest`, `DocumentationLinksTest`, the `mp-*`
  skills) — code *about* specs, not comments citing them.

# Why Stage A matters more than the sweep

The breadcrumbs feature added **51 task refs on its own**, taking the total from 241 to 292. At that
rate the cleanup is re-dirtied every two or three features. The rule is worth more than the sweep,
which is why it goes first and not last.

