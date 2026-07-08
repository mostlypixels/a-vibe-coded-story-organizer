## Guidelines for architecture and code style

Those are preferences to be taken into account during planning and development, but they can be questioned if better architecture options seem better.

This code will be used by junior developers.

### General

* Follow Laravel conventions unless there is a compelling architectural reason not to.
* Favor domain-driven design with small aggregates
* Use SOLID principles and DRY principles
* KISS principle
* Favor reusable components and templates
* Configuration should be kept in a single place. Avoid hard-coded values.
* Avoid magic numbers and magic strings. Use constants, enums or value objects.
* Favor composition over inheritance. Traits are a good alternative to inheritance.

### Where logic lives

Keep controllers, Blade templates, and Eloquent models thin. A controller action should read as:
resolve the model → authorize → delegate → return a response. Concretely, put each kind of logic here:

* **Input validation** → Form Requests (`app/Http/Requests`). Reusable rules → `app/Rules`
  (see `ValidMarkdown`). Validate enums with `Rule::enum(...)`.
* **Authorization** → Policies (`app/Policies`). See the Authorization rules below.
* **Reusable / multi-step domain workflows** → a dedicated Service or Action class. There is no
  `app/Services` layer yet; create one the first time an action needs non-trivial, reusable logic
  (for example, the position-swap logic currently duplicated in the Act/Chapter/Scene controllers is
  a candidate to extract into a shared trait or service). Do not add abstraction before there is a
  second caller — prefer a private controller method until reuse is real.
* **Model lifecycle invariants** legitimately live in `booted()` hooks — e.g. auto-assigning
  `position` on create, and auto-creating the main plotline. This is the intended exception to
  "no logic in models": *invariants and lifecycle* belong in the model; *application workflow* does not.
* **Constant/reference data** → `app/Support` (see `PlotlineColors`) or `app/Enums`.

## Planning and architecture

* Reuse existing project conventions before creating new ones.
* Do not introduce new patterns unless they provide clear value.
* If technical debt is introduced, explain why and document it.
*  Prefer maintainability and readability over clever or highly abstract solutions.

### Code style

* Use laravel code style conventions.

## Security and validation of user input

* Never trust user input.
* Escape output unless intentionally rendering trusted HTML.
* Validate input as early as possible, both on the front-end and the back-end.
* Validate all user input against business rules.
* Infer the proper validation rules from the database schema and/or field names.
* Avoid duplicated validation rules. Centralize them.
* Always use Laravel's Query Builder or Eloquent parameter binding. Avoid string concatenation in SQL queries.
* Validate uploaded files.

### Authorization

* Every controller action that reads or writes a resource must authorize it. Authorization flows from
  the owning `Project` via `ProjectPolicy` (`view` / `update` / `delete`); child resources authorize by
  walking up to their project (e.g. `$this->authorize('update', $scene->chapter->act->project)`).
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
(forced). See `documentation/architecture.md` → *Hidden from crawlers* for the full rationale.

## Testing

* Every new endpoint, controller action, and bug fix ships with a feature test. A bug fix adds a test
  that fails before the fix.
* Follow the existing style (`tests/Feature/ProjectTest.php`): plain PHPUnit, `use RefreshDatabase`,
  model factories, `actingAs($user)`, and the `route()` helper — never raw URLs.
* Cover, at minimum: the happy path, authorization (owner succeeds, non-owner gets 403), validation
  failures (`assertSessionHasErrors`), and any domain invariant touched (e.g. `position` assignment,
  the un-deletable main plotline).
* Tests run against in-memory SQLite; run the suite with `composer test`.
* Known coverage gap to fill as these areas are touched: there are no feature tests yet for Scenes,
  Acts, Chapters, or the Story overview — add `SceneTest` / `ActTest` / `ChapterTest` / `StoryTest`
  when you work in them.

### Documentation

The code must be understandable by junior developers — the code, the architecture, the pitfalls to
avoid, and the best practices to follow.

* Comment the code. Complex methods should explain the logic and intent, not just restate the code.
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

#### Changelog

* Every commit message body explains *why* the change was made and the intent behind it — this is the
  per-commit record (git already links, blames, and diffs it; no separate per-commit files).
* Maintain a single `CHANGELOG.md` at the repo root in [Keep a Changelog](https://keepachangelog.com)
  format: group entries under `## [Unreleased]` by `Added` / `Changed` / `Fixed` / `Removed`. Update it
  per feature or pull request (not per commit). Richer rationale for a change set belongs in the PR
  description, which links its commits automatically.


### Naming conventions

* Variable, methods and class names should be descriptive and meaningful
* Avoid abbreviations

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
