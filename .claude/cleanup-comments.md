# Handoff — comment cleanup, Stages A–D done, Stage E/G open

> [!WARNING]
> **Scratch. Delete when the cleanup lands.** Never cite this file from code or from
> `documentation/`. To remove that kind of citation is the purpose of this work.

## Read first

- **The plan** — `.claude/comment-cleanup-plan.md`. Stages, rubrics, and the two-layer
  verification contract. Current, except where *Scope changes* below overrides it.
- **The rule** — `.claude/rules/code-comments.md`. It loads when you edit a PHP file, and
  it governs every comment you write.
- **What shipped** — `git log master..HEAD`. The commit bodies carry the reasoning, and
  they are the record. This file does not repeat them.

## State

| | |
|---|---|
| Branch | `comment-cleanup-rules`, 9 commits ahead of `master`, **nothing pushed** |
| Working tree | clean, except untracked scratch files |
| `composer test` | green — 1615 tests |
| `npm run test` | green — 199 tests |
| `composer lint -- --test` | clean |
| `npm run build` | succeeds |
| Straggler greps | both return nothing, all six trees |

**Stage D is closed at five blocks** — 19 of 84 read, 69 deliberately left. The reason is
under *Stage D* below.

**Stages E and G are done.** Both came from a cold audit run after the sweep reported clean
(`.opencode/comment-audit.md`, 2026-08-06).

* **Stage E — an eighth citation class, 19 sites.** `finding 3`, `binding decision Q2`/`Q6`,
  `overview #2`, `D2, point N`, `Q3`, `the grilled … decision`, `refactor_codex finding 8`,
  plus two the sweep targeted and missed: `public-display task`, `the task's WARNING`.
  The audit found 16; a widened grep found 3 more it never sampled.
* **Stage G — 9 stale facts.** A wrong wrapper count, a "not-yet-wired" claim contradicted
  in the same file, 3 mentions of Tailwind's `purge` step (the project is on v4, which has
  none), a roadmap note, a rename history, and a hand-maintained 14-column count.

Nothing is left but `ship-pr`.

| Commit | What |
|---|---|
| `d5da6ca` | Stage A — split the code-comment rules out of the doc rules |
| `f66b0b7` | Stage B — `handoff.md` citations |
| `7acaec7` | Stage C — every plan-file citation, 165 files |
| `6c29786` | the 15 `resources/` defects `7acaec7` introduced |
| `b10a4ea` | the last 12 citations, in `app`/`tests`/`database` |
| `1fab9ce` | 2 false claims a cold review found in `resources/` |
| `cdde3a5` | `documentation/features/rich-text.md` — same false claim, third copy |
| `ae5473a` | what two cold reviews found: 5 defects in `b10a4ea`, 6 more sites, 2 false claims I introduced while fixing false ones |
| `50f69a6` | Stage D — five over-long blocks, +29/−28 |

**Every citation is gone. Stages A–C are done.** Four cold reviews cover this work, and
each found something. The last one on each change came back clean.

### Resume here — `ship-pr`

Nothing blocks it. Three things to carry into the PR description:

* the work is comments-only, except `d5da6ca` (the rules split) and `cdde3a5` (one
  sanctioned `documentation/` fix);
* Stage D closed at 5 of 84 blocks, and why;
* a cold audit found an eighth citation class after the sweep reported clean. Say so.
  Seven greps proved seven classes gone; none of them could see this one.

**One thing came out of this that is not comment work.** `draft-recovery.js`'s restore
does not reach a mounted Tiptap editor — a real bug, with a silent divergence case.
The comment carries a warning, not a fix. `.claude/fix-tiptap-restore.md` has the trace,
two fix options, and the user's open question about whether `localStorage` drafts should
exist at all. Do not fold it into this PR.

## Scope changes the user approved in flight

The plan does not record these. They override it.

1. **Plan-document citations go too, not only `task NN`.** Covers `00-overview.md`,
   `standing-issues.md #N`, `grill decision N`, `overview decision #N`, `invariant N`,
   `§N` and `resolution-log.md`. The user's reason: *"scope always changes subtly during
   dev, so the file is not a source of truth: it's history."*
2. **`resources/` is in scope** — `.js`, `.test.js`, `.blade.php`, `.css`, and
   `resources/epub-schemas/README.md`.
3. **`documentation/` is normally untouched** — the Layer 1 check requires an empty diff
   there. `cdde3a5` is the one sanctioned exception: a cold review found the same false
   claim in a permanent doc, and the user asked for it separately. Keep such fixes in
   their own commit.

`expanded/*.md` and `spec.md` citations stay. They survive into `.specs/shipped/`.

### Why `invariant #1` went anyway, though `expanded/*.md` is citable

The numbered list it points at *does* survive (`codex/expanded/attribute-timeline.md`),
so the pointer was not rot. It went for three better reasons:

* The rule has a permanent home — `documentation/features/codex.md`'s *"Leading-anchor
  anchor at Start"* callout. A spec is history; `documentation/` is current.
* A number depends on list order. Insert an invariant at the top, renumber, and five
  comments cite the wrong rule with nothing broken to notice — quieter than a dead path.
* All five already stated the rule in the same sentence, so the digit carried nothing.

**The general form: prefer a rule's name to its number.** "The Start baseline" greps;
`#1` does not survive a renumber.

## Failure mode 7 — every reviewer adds correct finds AND errors

The pattern held across four cold reviews plus an external audit: each one found something
real, and each one was also wrong about something. The useful split is not reviewer quality,
it is **claim type**:

* **Grep-shaped claims survive.** The opencode audit's citation inventory was 16 for 16.
* **Counted or judged claims fail.** The same audit said six `icon-*` wrappers (there are
  eight), and two `purge` mentions (there are three). It also miscounted the migration and
  factory docblocks, 55 vs 43 and 14 vs 16.

Two consequences, both load-bearing:

1. **Verify before you edit, always.** Every error above was caught by one grep. This is a
   process, not a talent — a smarter reviewer does not remove the need for it.
2. **A new audit is worth running for a new *class*, not for more sites.** Classes are cheap
   to check: the class exists or it does not. Site inventories need a full re-verification
   pass, which costs more than the audit did.

The deeper reason this never converges: **there is no oracle.** No test can tell you a comment
is false. So close the work on enumerated classes with greps that prove them gone — never on
"no defects remain", which cannot be shown.

## Six failure modes this cleanup hit. Stage D hits them again.

1. **A line-based grep misses a wrapped citation.** `per overview` / `decision #4` split
   across two comment lines. Always `rg -U --multiline-dotall`, and `[\s*/]+` between
   words, never `\s+` — the ` * ` docblock continuation is not whitespace.
2. **A dying citation holds up a claim.** Cut the number and the claim stands unsupported.
   Ask what the sentence still asserts, then verify *that* against the code. **This was
   the single most common defect, and it recurred after every "all clear".**
3. **Some comments are already false**, and the citation hides it. Found across the pass:
   "not wired in yet" (breadcrumbs, autosave-field), "nothing calls it yet"
   (`HtmlTokenizer`), "packaging is not exercised here" (`EpubExporterTest`, where 35
   tests call `export()`). Expect more in Stage D.
4. **A scripted bulk edit leaves wreckage.** A `node -e` pass cut citations mid-sentence
   and left sentences with no verb, no closing punctuation, and fused pairs. It produced
   all 15 defects in `6c29786`. **Do not script the rewrite.** Read each block in full.
5. **A widened grep changes the answer.** Three citation classes hid from the original
   pattern: the plural `tasks 01-06`, the numberless `the task file` / `the task's own
   note`, and `open-questions.md #N`. Widening it turned "clean" into 12 survivors.
6. **A citation does not have to look like a path.** After the widened grep returned
   nothing, a cold check still found three more classes, and the second grep found six
   more sites:
   * **`task-11`** — a hyphen defeats `[Tt]ask[\s*/]+[0-9]`.
   * **Verb forms.** `this task adds`, `the task wants`, `later tasks`, `the plan said`.
     No number, no filename, same rot.
   * **`.specs/planned/<date>/<feature>`** — a folder path that dies the moment the
     feature ships. Two migrations already cited folders now under `.specs/shipped/`.
   Both patterns are in *Verification* below. Run **both**.

## Verification — non-negotiable

**Layer 1**, after every step:

```bash
composer test                 # green
npm run test                  # green
composer lint -- --test       # clean
npm run build                 # succeeds
git diff -- documentation/    # must be empty
```

Plus the straggler grep, the widened pattern, over every in-scope tree:

```bash
rg -U --multiline-dotall -n -i \
  "([Tt]asks?[\s*/]+(file|[0-9])|task's[\s*/]+own|handoff\.md|standing-issues|resolution-log|open-questions|00-overview|plan/[0-9]{2}-|grill[\s*/]+decision|overview[\s*/]+decision|invariant[\s*/]+#?[0-9]|§[0-9])" \
  app/ tests/ database/ config/ routes/ resources/
```

And the second pattern — the shapes that carry no number and no filename:

```bash
rg -U --multiline-dotall -n -i --color=never \
  "([Tt]he[\s*/]+plan[\s*/]+(said|says|wants|calls)|[Tt]his[\s*/]+task[\s*/]|[Tt]he[\s*/]+task[\s*/]+(wants|says|calls|adds)|later[\s*/]+tasks?|earlier[\s*/]+tasks?|next[\s*/]+task|task-[0-9]|a[\s*/]+later[\s*/]+step|the[\s*/]+spec[\s*/]+(said|says))" \
  app/ tests/ database/ config/ routes/ resources/

rg -n --color=never '\.specs/(planned|draft|expanded)/' \
  app/ tests/ database/ config/ routes/ resources/
```

The first two must return nothing. The third returns hits by design — `SpecDraftCommand`
and `SpecsStatusConsistencyTest` are code *about* the spec lifecycle, so those paths are
their subject matter, not citations. Every other hit is a citation to check: a
`planned/` or `draft/` path rots the moment the feature changes status.

All patterns: read every hit before you cut it — a number that glosses its own rule in
the same sentence may deserve a rename rather than a deletion.

**Layer 2**: a fresh general-purpose agent per step. Give it the git ref, the changed
files, and `.claude/rules/code-comments.md`. **Forbid it from reading this file** — its
value is that it starts cold. Ask for PASS/FAIL per file on information preservation
(blocking), factual accuracy (blocking), STE, and comments-only. Tell it to verify every
claim against the code and to existence-check every cited path.

Ask it to be adversarial. The run on `resources/` found two blocking failures in a pass
already declared clean, plus two pre-existing errors nobody had questioned. Across the
whole cleanup this loop has caught 7 defects in C1, 1 in C3–C5, 2 grep stragglers, and
2 false claims after the repair commit. **Do not self-certify a rewrite.**

## Gotchas

- **Pint forces non-comment changes.** `{@see \Fully\Qualified\Name}` in a docblock trips
  `fully_qualified_strict_types` and Pint adds a `use` import. One is already in the diff
  (`tests/Feature/PublicationSettingTest.php`). Write class names as prose instead —
  `ProjectGraphImporter` and `RevisionController` both do.
- **Test names changed, not only comments.** `describe()`/`it()` strings in
  `resources/js/**/*.test.js` carried citations. The diff is not strictly comments-only.
- **The assertion count oscillates** between 5852 and 5853 with no code change.
  Pre-existing. The test count (1615) is stable — trust that. One run reported a single
  failure at 5850; it never reproduced and was never identified. The suspect is the epub
  defaults-regression guard, whose own comment documents a wall-clock race under the
  parallel runner.
- **`rg` output mangling.** With colour on, `rg` can strip the matched text from the
  printed line, so `invariant #1` prints as `n`. It looks like file damage and is not.
  Use `--color never` when a match must be read literally.
- **Cosmetic, ~25 sites.** Excised mid-line text left ragged short lines. Not blocking;
  re-wrap anything you touch. Only the blocks already touched were re-wrapped.
- **`master` is protected.** Branch → PR → green `tests` → squash-merge.
- **`CHANGELOG.md` needs its dated section** for this PR. `ship-pr` handles it.

## Stage D — the remaining work

No citations are left. Stage D shortens long comments that carry none.

Re-counted 2026-08-06 with a block scan over `app/` (≥6 consecutive comment lines,
words counted after the `*`/`//` markers are stripped). The plan's figures were low:

| Step | Size | Blocks — plan said | actual |
|---|---|---|---|
| D1 | 200+ words | 9 | **14** |
| D2 | 120–199 words | 51 | **70** |
| — | 60–119 words | 158 | 196 — leave alone |
| — | under 60 words | 218 | 235 — leave alone |

**Cut**: narration of how the code got here; a restatement of what the next line plainly
does; rationale `documentation/` already holds, replaced by a link.
**Keep**: invariants, pitfalls, why-not-the-obvious-thing, anything documenting a fixed bug.

Preserve these four in any rewrite:
- `Revision::prunable()`'s `MAX(id)` warning ("do not simplify it back")
- `ProjectGraphImporter`'s replay-order `[!IMPORTANT]`
- `AccentFolder`'s 1-char→1-char offset invariant
- `EpubExporter`'s two isolation rules

The rule says: **don't shorten an existing long comment without asking.** Stage D is that
asking, done in bulk — so expect a modest reduction, and **if it starts to feel like bulk
deletion, stop.** The rubric is the point, not the word count.

### All 14 D1 blocks, read

The plan's central finding holds. Ten are load-bearing and should not be touched.

| Block | Verdict |
|---|---|
| `ProjectGraphImporter::importRevisions` 811 (476w) | **trim** — the `addRevisions()` comparison is narration |
| `EpubExporter` 29 (401w) | leave — C3 rewrote it; it is the rule's worked example |
| `RevisionReverter::restore` 181 (327w) | leave — lock order, re-validation, read-back, transaction |
| `Revision::prunable` 84 (321w) | leave — protected |
| `ThemeRampCommand` 10 (286w) | **trim** — the "five hand-eyeballed sRGB ramps" aside |
| `ProjectGraphImporter` 33 (272w) | leave — caller contract |
| `ProjectSearch` 22 (255w) | leave — binding design decisions |
| `RevisionHistory` 18 (239w) | leave |
| `AccentFolder` 7 (237w) | leave — protected |
| `ThemeStyleBlock` 9 (231w) | leave — all why-not |
| `StaticSiteExporter::addBook` 582 (211w) | **trim** — "`chapterHref()` stays untouched" is history; ragged wrap |
| `ArchiveValidator` 11 (206w) | **trim** — the numbered list restates the code |
| `DiffHtmlRenderer` 13 (201w) | leave — safety boundary |
| `RevisionSummarizer` 15 (201w) | leave |

### What was done, and why it stopped — `50f69a6`

**It stopped because the session ran out of quota, not because the work was complete.**
Five D2 blocks were sampled before that. Four of five came back "leave alone", which
matched the D1 ratio of 10 of 14 — a reason to expect a low yield, not a reason to stop.

| Block | What happened |
|---|---|
| `ProjectGraphImporter::importRevisions` | cut the `addRevisions()` symmetry aside |
| `EpubExporter::addAppendixSection` | cut a 3-bullet list of the `if` returns below it |
| `ThemeRampCommand` | cut a comparison to five ramps that **no longer exist** |
| `ArchiveValidator` | **not a trim** — the list is an index into `validate()`'s own `// Check N` markers, and it omitted `validateChapterCovers()`. Added. |
| `StaticSiteExporter::addBook` | "stays untouched" was history; the trap under it became a `[!WARNING]` |

**Stage D is closed at five blocks. The remaining 69 D2 blocks stay unread, on purpose.**

The quota ended the session that was working them. The decision to stop came later, and
rests on three things:

* **Yield.** 5 changes out of 19 blocks read. The rubric returned "leave alone" at every
  size, which matches the plan's own premise: no large win exists beyond the citations.
* **A cold second opinion.** An independent audit (`.opencode/comment-audit.md`, 2026-08-06)
  read the same long-block class — `ProjectSearch`, `ThemeStyleBlock`, `SceneReferenceMatcher`,
  `AttributeTimeline` — and called it "a tension, not a defect to rush".
* **The judgment is not available yet.** To trim a long block you must know if its rationale
  is load-bearing. That depends on the feature being settled, and this app is in active
  development. The user's reason: a thorough review of a feature that is still moving is
  wasted work.

Reopen it when the features it touches stop changing, not before.

## Left undone — a seventh citation class, never swept

The D2 sample found it. Not rot: nothing is dead, and `expanded/` is citable. But these
do not resolve without a content search, which is most of what a citation is for.

* **7 sites cite a bare filename** — `data-model.md`, `architecture.md`, `ui.md`,
  `spec.md`. There are **16** files named `data-model.md` in `.specs/`, 24 named
  `architecture.md`, 23 `ui.md`, 25 `testing.md`.
* **30 sites use a partial path** like `expanded/ui.md`, which 23 files match.
* Three write the arrow form `.specs → import → architecture.md`.

The sharpest case: `ContentSanitizer:32` cites *"the 'raw-HTML passthrough hole' in
architecture.md"*. That phrase is in **two** different `expanded/architecture.md` files,
and **not** in `documentation/architecture/README.md` — which is what a reader opens first.

```bash
rg -n --color=never -o "(see |in |per )[a-z][a-z0-9-]*\.md('s)?" \
  app/ tests/ database/ config/ routes/ resources/ | rg -v '\.specs/|documentation/'
```

The user was told and deferred it. Do the 7 bare ones first if it is picked up — they
are the ambiguous ones. The 30 partial paths are mechanical path-completion, not
judgment.

## Suggested skills

- **`ship-pr`** — handles the `CHANGELOG.md` section and the protected-branch ritual.
  **Only after Stage D**; the user wants one PR at the end.
- **`grilling`** — only to stress-test a Stage D judgment call before applying it across
  the 70 mid-size blocks.
- Nothing else. No `mp-*` skill applies, and there is no UI to drive.
