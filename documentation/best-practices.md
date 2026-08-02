# Best practices

Practical rules for building features safely in this codebase. See
[architecture](architecture.md) for the big picture and [code style](code-style.md) for
formatting.

## Where logic lives

Keep controllers, Blade templates, and Eloquent models thin. A controller action should read
as **resolve the model → authorize → delegate → return a response**. Put each kind of logic
in its home:

| Logic | Home |
| --- | --- |
| Input validation | Form Requests (`app/Http/Requests`); reusable rules in `app/Rules` |
| Authorization | Policies (`app/Policies`) |
| Reusable / multi-step domain workflow | A Service or Action class (e.g. `RevisionRecorder`, `RevisionPurger`) |
| Model lifecycle invariants | Model `booted()` hooks |
| Constant / reference data | `app/Support`, `app/Enums` |

> [!NOTE]
> The `app/Services` layer was introduced by the Codex feature — `AttributeTimeline`
> (temporal attribute resolution + gap-free mutations) and `CodexMediaService` (file storage,
> single-cover rule, disk cleanup) — because both are non-trivial *and* have real second
> callers (controllers, model helpers, and the seeder). **Still, do not add a service before
> reuse is real** — a private controller method is fine until then. The move-up/move-down
> `swapPosition` logic that used to be copied across the Act/Chapter/Scene controllers is the
> textbook example: once a real second (and third) caller existed, it was extracted into the
> `HasSiblingPosition` model trait (each model just declares its `siblingScopeColumn()`).

> [!WARNING]
> Keep invariant-enforcing logic in a **service method, not only a `booted()` hook** when a
> seeder must produce it: `DatabaseSeeder` runs `WithoutModelEvents`, so hooks never fire.
> The Codex's Start-baseline invariant lives in `AttributeTimeline::ensureBaseline` precisely
> so `MelusineSeeder` can call it directly.

> [!WARNING]
> "Keep logic out of models" has one deliberate exception: **invariants and lifecycle** belong
> in the model (`booted()` hooks assigning `position`, auto-creating the main plotline).
> *Application workflow* does not.

> [!WARNING]
> When a Form Request's rules must also be validated **outside** an HTTP request (e.g. an
> archive importer validating untrusted config extracted from a zip — see
> `ProjectGraphImporter` validating against `UpdatePublicationSettingRequest::configRules()`),
> expose them as a `public static function` returning the rule array, called from both
> `rules()` and the non-HTTP caller. **Do not name it `validationRules()`** — that name is
> already a non-static method on the `FormRequest` base class, and shadowing it fatals at
> class load. `configRules()` is the established name for this pattern in this codebase; reuse
> it (or the same suffix convention) rather than reinventing a name per feature.

## Security & validating user input

- **Never trust user input.** Validate as early as possible, on both the front end and the
  back end, and validate against business rules — not just types.
- Centralize validation in Form Requests; don't duplicate rules across store/update by hand
  when they can share a base. Infer rules from the schema and field names.
- Escape output by default. Only render trusted HTML intentionally (e.g. `Str::markdown()` on
  scene contents, which is authored by the project owner).
- Always use Eloquent / the query builder with parameter binding. **Never** concatenate user
  input into SQL.
- Validate uploaded files (type, size) before storing them.

## Authorization

- Every controller action that reads or writes a resource authorizes through the owning
  `Project` via `ProjectPolicy`. Child resources walk up:
  `$this->authorize('update', $scene->chapter->act->project)`.
- Mirror the check in the Form Request's `authorize()`:
  `$this->user()->can('update', $this->route('project'))`.
- Never rely on route model binding or hidden form fields for access control.
- **Always test the negative case:** a non-owner must get a `403`.

## Testing

- Every new endpoint, controller action, and bug fix ships with a feature test. A bug fix
  first adds a test that fails **before** the fix.
- Follow the existing style (`tests/Feature/ProjectTest.php`, `tests/Feature/SceneTest.php`):
  plain PHPUnit, `use RefreshDatabase`, model factories, `actingAs($user)`, and the `route()`
  helper — never raw URLs.
- Cover at minimum: the happy path, authorization (owner succeeds / non-owner `403`),
  validation failures (`assertSessionHasErrors`), and any domain invariant touched (e.g.
  `position` assignment, the un-deletable main plotline).
- Tests run against in-memory SQLite. Run the suite with `composer test`.

> [!NOTE]
> Acts, Chapters, Scenes and the Story overview each have a dedicated feature test now
> (`ActTest` / `ChapterTest` / `SceneTest` / `StoryTest`) covering CRUD, authorization,
> validation, the `position` invariant and reordering. Keep them in step as you touch those
> controllers; `SceneTest` is still the pattern to copy for anything new.

## Database & queries

- **Eager-load** the relations a view renders (`->with(...)`) to avoid N+1 queries —
  especially the nested act → chapter → scene tree on the story overview.
- Keep index-page filtering, sorting, and search in the controller's `index` method (the
  existing convention), not in Eloquent query scopes.
- Add indexes deliberately, based on real query patterns. Keep queries readable; avoid raw SQL
  unless necessary.
- Wrap multi-step writes in a database transaction.

### Derived columns: store the answer when the question has a constant answer

Sometimes the cheapest read is one that computes nothing. `revisions.summary_html` /
`change_count` are the example: a diff between two **immutable** rows can never change, so
it is computed once at write time and stored, and the history list renders without diffing
anything. Compute-at-read would have made the first visitor to a ninety-revision history
pay for ninety diffs, and caching only moves that cost around.

Store a derived value when all three hold:

1. **The inputs are immutable**, so the stored answer cannot silently become wrong.
2. **The read is much more frequent than the write**, or much more expensive.
3. **A stale value is cosmetic, not dangerous** — you can name what breaks and live with it.

What it buys, and what it costs:

> [!WARNING]
> A derived column is a promise you have to keep in **every** write path, including the ones
> that are not the obvious one. `revisions.summary_html` is written by the live recorder, by
> the import replay, *and* by the baseline-backfill migration — miss one and that era of
> rows is silently summary-less. It also owes a **backfill migration** the day the
> computation changes, and it can be invalidated by deletes elsewhere: pruning an old
> revision leaves its successor's summary describing a predecessor that no longer exists.
> That staleness was accepted deliberately (recomputing during a mass prune turns a cheap
> `DELETE` into an O(n) diff job) and is documented where a reader will meet it.

The rule of thumb: **derive at read until a read is provably expensive**, then store — and
write down what goes stale, in the same commit.

## Developer tooling (shells, package managers)

- Choosing a shell, package manager, or workflow command portably (any OS) is governed by
  [`.claude/conventions/tooling.md`](../.claude/conventions/tooling.md): pick tools by
  **availability**, never by OS name; never mix one shell's syntax into the other's tool; the
  **lockfile** decides the package manager; canonical commands are defined once
  (test = `composer test`).

## Documentation & changelog

- Keep this `documentation/` folder synchronized with the code — update it whenever
  architecture or workflows change. Explain **why**, and use GFM alert callouts
  (`> [!WARNING]`, `> [!NOTE]`) for pitfalls and tips.
- Every commit message body explains **why** the change was made — that is the per-commit
  record.
- Maintain a single root `CHANGELOG.md` in [Keep a Changelog](https://keepachangelog.com)
  format, adapted so the heading answers *when something shipped*: each PR adds its own
  dated `## YYYY-MM-DD — <title> (#PR)` section at the top, below `[Unreleased]`, grouped by
  `Added` / `Changed` / `Fixed` / `Removed`. Update it per feature or pull request, not per
  commit; `[Unreleased]` holds only work not yet merged to `master`. Leave the `(#PR)`
  suffix off — `scripts/pr-land.sh` stamps it once the number exists.
- Keep entries to one line each (~20 words): what changed, not how or why, no class names
  or file paths, 1–5 entries per PR. The file is never pruned, so it has to stay readable
  years from now; rationale belongs in the PR description. Sections dated before
  `2026-08-02` predate this rule — don't imitate them.

## Testing UI state: assert on semantic hooks, not Tailwind classes

When a view toggles active/selected state on an element whose only signal is a swapped
Tailwind class (e.g. a tab `<button>`), give it a real hook — `aria-current`,
`aria-pressed`, `aria-selected`, or a `data-active` attribute — and assert on **that** in
feature tests.

> [!WARNING]
> Asserting on class-token substrings (`assertSee('text-nav-content border-accent')`) is
> brittle: the tokens aren't unique to one element, so a later test covering a *second*
> instance of the pattern can't tell which one is active and forces a rework of the
> earlier assertions.

Add the hook in the **first** change that introduces the pattern so every sibling reuses
it — it doubles as an accessibility win. Only fall back to a class-token assertion when
explicitly sanctioned, scoping the regex to a uniquely-identifying ancestor.
