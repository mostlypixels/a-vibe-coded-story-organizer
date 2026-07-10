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
| Reusable / multi-step domain workflow | A Service or Action class |
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
> Coverage gaps to fill as you touch them: there are still no feature tests for Acts,
> Chapters, or the Story overview. `SceneTest` is the pattern to copy.

## Database & queries

- **Eager-load** the relations a view renders (`->with(...)`) to avoid N+1 queries —
  especially the nested act → chapter → scene tree on the story overview.
- Keep index-page filtering, sorting, and search in the controller's `index` method (the
  existing convention), not in Eloquent query scopes.
- Add indexes deliberately, based on real query patterns. Keep queries readable; avoid raw SQL
  unless necessary.
- Wrap multi-step writes in a database transaction.

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
  format: entries under `## [Unreleased]`, grouped by `Added` / `Changed` / `Fixed` /
  `Removed`, updated per feature or pull request (not per commit).
