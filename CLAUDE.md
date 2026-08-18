## Guidelines for architecture and code style

Those are preferences to be taken into account during planning and development, but they can be questioned if better architecture options seem better.

This code will be used by junior developers.

### Commands

The canonical commands for this project (referenced by the skills and agents in `.claude/`):

* Test: `composer test` (in-memory SQLite, runs in parallel via paratest; one DB per process, so tests must not assume shared state)
* JS test: `npm run test` (vitest; co-located `resources/js/<name>.test.js` files next to the source they cover)
* Lint/format: `composer lint` (check only: `composer lint -- --test`)
* Build frontend: `npm run build`
* Dev server: `php artisan serve`
* All three checks at once: `bash scripts/verify.sh` (add `--filter <pattern>` for a single
  PHP test). Prefer it over running the first three by hand — one call, one summary.

These are the same commands whether run against a local PHP/Node install or inside
Docker (`make test`, `make lint`, `make shell` then `npm run build`, `make up`) — see
`documentation/development/docker.md`. Docker is available for anyone without a local PHP/Node
setup; it isn't required, and it doesn't replace the commands above.

`master` is protected: direct pushes are rejected. All changes ship as branch → PR →
green `tests` CI check → squash-merge (0 approvals required; self-merge is fine).

Reusable workflow scripts live in `scripts/` (see its README); check there before
inlining a command sequence in a skill or agent.

### General

* Follow Laravel conventions unless there is a compelling architectural reason not to.
* Favor domain-driven design with small aggregates
* Favor composition over inheritance. Traits are a good alternative to inheritance.
* **Toolchain & shell rules live in `.claude/conventions/tooling.md`** — select the shell by tool availability (not OS), never mix one shell's syntax into the other's tool, and let the lockfile decide the package manager. Read it before running shell commands.

### Where logic lives

Keep controllers, Blade templates, and Eloquent models thin. A controller action should read as:
resolve the model → authorize → delegate → return a response. Concretely, put each kind of logic here:

* **Input validation** → Form Requests (`app/Http/Requests`). Reusable rules → `app/Rules`
  (see `ValidMarkdown`). Validate enums with `Rule::enum(...)`.
* **Authorization** → Policies (`app/Policies`). See the Authorization rules below.
* **Reusable / multi-step domain workflows** → a dedicated Service or Action class in
  `app/Services` (see `ProjectSearch` for the template: the controller resolves + authorizes,
  the service owns the queries and domain logic). Extract further candidates the same way —
  e.g. the position-swap logic currently duplicated in the Act/Chapter/Scene controllers. Do
  not add abstraction before there is a second caller — prefer a private controller method
  until reuse is real.
* **Model lifecycle invariants** legitimately live in `booted()` hooks — e.g. auto-assigning
  `position` on create, and auto-creating the main plotline. This is the intended exception to
  "no logic in models": *invariants and lifecycle* belong in the model; *application workflow* does not.
* **Constant/reference data** → `app/Support` (see `PlotlineColors`) or `app/Enums`.

## Planning and architecture

* Reuse existing project conventions before creating new ones.
* If technical debt is introduced, explain why and document it.

### Feature specs live under `.specs/`

Use the `mp-draft-spec` skill. Folder location and `status:` frontmatter must agree —
`tests/Unit/SpecsStatusConsistencyTest` enforces it. Lifecycle: `.specs/README.md`.

## Security and validation of user input

* Validate input as early as possible, both on the front-end and the back-end.
* Infer the proper validation rules from the database schema and/or field names.
* Avoid duplicated validation rules. Centralize them.

### Authorization

* Every controller action that reads or writes a resource must authorize it. Authorization flows from
  the owning `Project` via `ProjectPolicy` (`view` / `update` / `delete`); child resources authorize by
  walking up to their project (e.g. `$this->authorize('update', $scene->chapter->act->book->project)`).
  The manuscript hangs off a `Book`, so every story walk goes through it; `Book` has no policy of its
  own either (`$this->authorize('update', $book->project)`).
* Mirror the same check in the Form Request's `authorize()` (`$this->user()->can('update', ...)`).
* Never rely on route model binding or hidden form fields alone for access control.
* Always cover the negative case in tests: a non-owner must get a 403.
* **The one exception** is the global "hidden from crawlers" setting: `CrawlerSetting` is a
  singleton owned by no `Project`, so it does *not* use `ProjectPolicy`'s walk — it is behind
  `auth` and `UpdateCrawlerSettingRequest::authorize()` is simply `$this->user() !== null` (any
  authenticated user). Do not "fix" this into a project walk.

### Hidden from crawlers (feature note)

Whole-site search-engine visibility is one global `CrawlerSetting` singleton (read via
`CrawlerSetting::current()`, lazily seeded from `config/crawlers.php`, **default hidden**). A
dynamic public `/robots.txt` route (`RobotsTxtController` + `RobotsTxtGenerator`, outside the
`auth` group) renders it live — the **static `public/robots.txt` was removed** so the route is
reached; do not re-add it. The `x-robots-meta` component is the single source of the
`noindex, nofollow` tag, wired into `app`/`guest`/`welcome` (toggle-governed) and `public`
(forced). See `documentation/architecture/README.md#rendering-and-public-access` for the rationale.

## Testing

* Every new endpoint, controller action, and bug fix ships with a feature test. A bug fix adds a test
  that fails before the fix.
* Follow the existing style (`tests/Feature/ProjectTest.php`): plain PHPUnit, `use RefreshDatabase`,
  model factories, `actingAs($user)`, and the `route()` helper — never raw URLs.
* Cover, at minimum: the happy path, authorization (owner succeeds, non-owner gets 403), validation
  failures (`assertSessionHasErrors`), and any domain invariant touched (e.g. `position` assignment,
  the un-deletable main plotline).
* Tests run against in-memory SQLite; run the suite with `composer test`.
* **Never verify anything against the dev database.** `php artisan tinker` uses the default
  connection — `database/database.sqlite`, real data — so a throwaway script that creates
  models leaves them there. Scratch verification ("does this query throw?", "does `sum()`
  return 0 or `null`?") goes through `bash scripts/probe-test.sh '<php>'`, which writes a
  temporary feature test, runs it, and deletes it whatever the outcome: `phpunit.xml` forces
  `:memory:` with `force="true"`, so a probe cannot reach dev data whatever `.env` says, and
  factories and `RefreshDatabase` come for free. When a probe genuinely needs the seeded
  data, wrap it in a transaction and roll back.
* Books, Scenes, Acts, Chapters, and the Story overview each now have a dedicated feature test
  (`BookTest` / `SceneTest` / `ActTest` / `ChapterTest` / `StoryTest`) covering CRUD, authorization,
  validation, the `position` invariant, and reordering. Keep them in step as you touch those
  controllers.

### Documentation

Doc, spec and `.claude/` prose rules: `.claude/rules/documentation.md` (loads when you edit
those paths). It also governs commit bodies and PR descriptions — read it before writing them.
Start at `documentation/README.md`, then open only the guide for the feature you change.

Code comments follow their own rules — ASD-STE100 Simplified Technical English, and never a
citation to a temporary file: `.claude/rules/code-comments.md` (loads when you edit a PHP file).

#### Changelog

Every commit body explains *why*. `CHANGELOG.md` gets one dated section per PR — format and
entry style: `.claude/rules/changelog.md`.

### Database

* Add indexes deliberately based on query patterns.
* Keep database queries readable.
* Avoid raw SQL unless necessary.
* Use database transactions for multi-step write operations, unless working on a database type that does not support transactions.
* Eager-load the relations a view renders (`->with(...)`) to avoid N+1 queries — especially the nested
  act → chapter → scene tree on the Story overview.
* Keep index-page filtering, sorting, and search in the controller's `index` method (the existing
  convention), not in Eloquent query scopes.

### Tailwind

* Create components for reusable parts of the UI, including buttons, titles, cards, tables, etc.
* Reuse existing Tailwind components before creating new ones.

### Frontend

* Keep presentation logic out of Blade templates.
* Prefer semantic HTML.
* Ensure keyboard accessibility.

#### Font choice

Never add a second path from a stored value into rendered CSS. Theme and font
preferences resolve through `ThemePreset::resolve()` / `FontChoice::resolve()` only —
both server-side (`x-theme-style`) and in `resources/js/font-preview.js`'s lookup map —
so a slug that isn't in `config/themes.php` / `config/fonts.php` is a no-op everywhere,
never a value read from `input.value` or interpolated raw. See
`documentation/interface/fonts.md`.
