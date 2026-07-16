# Task 03 — Route, SearchController, SearchRequest

## Scope

Wire the HTTP layer around `ProjectSearch` (task 02):

* Route in `routes/web.php`, inside the existing `auth` middleware group, alongside the other
  `projects.*` routes:
  ```php
  Route::get('/projects/{project}/search', [SearchController::class, 'index'])->name('projects.search.index');
  ```
* `app/Http/Requests/SearchRequest.php`:
  * `authorize()`: `$this->user()->can('view', $this->route('project'))`.
  * `rules()`: `q` → `nullable|string|max:500`; `mode` → `[Rule::enum(SearchMode::class)]` with
    a sensible default applied in the controller (not a `required` rule — see below).
* `app/Http/Controllers/SearchController.php` (single-action shape, like `StoryController`):
  * `$this->authorize('view', $project)`.
  * Read `q` and `mode` from the validated request; default `mode` to `SearchMode::AllTerms`
    when absent (per the binding "default mode = AND" decision).
  * If `q` is null/blank, skip calling `ProjectSearch` entirely and pass `results = null` to
    the view (an empty query is the normal landing state, not an error).
  * Otherwise call `ProjectSearch::search($project, $q, $mode)` and pass the grouped results.
  * Return `view('search.index', [...])`.

This task does **not** build the Blade view (task 04) or the nav link (task 05) — return a
minimal/stub view reference is fine to land if task 04 isn't done yet, but prefer sequencing
03 before 04 so the real view exists when this task's feature tests run. Do not add
pagination/result-limiting here (out of scope — see `search_pagination` draft spec referenced
in `00-overview.md`).

## Depends on

* Task 02 (`ProjectSearch`).

## Key decisions already made (binding, see `00-overview.md`)

* Authorization flows from the Project via `ProjectPolicy::view`, mirrored in both the
  controller and the Form Request — per `CLAUDE.md` § Authorization.
* Empty/absent `q` → render the form, no results, no validation error.
* Default mode = AND (`SearchMode::AllTerms`).
* No AJAX — plain `GET`, full page render.

## Docs to consult

* `expanded/architecture.md` → *Route*, *Controller*, *Authorization* sections — this task
  implements those almost verbatim.

## Tests

`tests/Feature/SearchTest.php` (new — plain PHPUnit, `RefreshDatabase`, factories,
`actingAs($user)`, `route('projects.search.index', $project)`, per `CLAUDE.md` § Testing):

* **Authorization**: a non-owner requesting another user's project search gets a 403.
* **Empty query**: `GET` with no `q` param returns 200 and does not throw a validation error
  (`assertSessionHasNoErrors` or equivalent) — the page renders with no results.
* **Validation**: an invalid `mode` value (not one of the enum cases) triggers
  `assertSessionHasErrors('mode')`.
* **Happy path (end-to-end through the controller)**: seed at least one matching entity,
  submit a real query, assert a 200 response — full result-content assertions belong in task
  04's view test once the real template exists, but this task's test should at minimum prove
  the controller wires `ProjectSearch`'s output into the view without erroring.
* **Default mode applied**: submitting `q` with no `mode` behaves as AND (reuse a fixture from
  task 02's AND-mode test to prove the controller defaults correctly, not just the service).
