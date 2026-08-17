# Code style

The goal is code a junior developer can read, extend, and trust. When in doubt, match the
surrounding code.

## Formatting

- **Laravel Pint** is the formatter. Run `composer lint` before committing (check without
  writing: `composer lint -- --test`); it applies the Laravel preset. Do not hand-fight its
  output.
- Follow standard Laravel conventions unless there is a compelling architectural reason not to.

## Naming

- Variables, methods, and classes are descriptive and meaningful. **Avoid abbreviations.**
- Titles for books/acts/chapters/scenes are freeform and never encode their number — the
  `position` is the number (see
  [architecture](architecture.md#book--act--chapter--scene-ordering)).

## Modern PHP is welcome

The codebase uses modern PHP idioms throughout, and you should too:

- **Arrow functions** (`fn ($query) => ...`) for short closures, especially query builder
  callbacks.
- **`match` expressions** over long `if`/`switch` ladders (see `SceneStatus::label()`).
- **Backed enums** for fixed sets of values (see `app/Enums`).
- **Constructor property promotion**, typed properties, and typed return values (relationship
  methods declare `: BelongsTo` / `: HasMany`).
- **First-class validation helpers** like `Rule::enum(...)` and `Rule::exists(...)`.

```php
// Idiomatic: arrow function + when() + match
$scenes = Scene::query()
    ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', "%{$term}%"))
    ->orderBy($sort, $direction)
    ->get();
```

> [!NOTE]
> An older guideline discouraged "shorthand PHP8 functions." It was removed because it
> contradicted the actual codebase. Modern, readable PHP is the standard here.

## Comments

- Comment the code. Explain **why**, not just **what** — the code already says what.
- Complex methods (query building, position swapping, lifecycle hooks) get a short comment
  describing intent and any non-obvious constraint.

```php
// position is omitted here on purpose: the Scene::creating() hook assigns the
// next position scoped to the parent chapter.
```

## Controllers, requests, models

- Keep controllers thin: **resolve → authorize → delegate → respond**. See
  [best practices](best-practices.md#where-logic-lives) for what goes where.
- Validation belongs in Form Requests (`app/Http/Requests`), not inline in the controller.
- Type-hint Form Requests in controller actions so validation runs automatically and
  `$request->validated()` returns only the allowed fields.
- Models declare `$fillable`, `$casts`, and typed relationship methods; domain **invariants**
  (not workflow) live in `booted()` hooks.

### Shared controller concerns

Repeated action shapes live in `app/Http/Controllers/Concerns`, composed with `use`. Reach for
the existing one before re-typing the pattern:

| Concern | Use it for | Why it is shared |
|---|---|---|
| `RecordsManualRevisions` | `update()` on any entity with autosaved fields | the snapshot-then-record dance around the save |
| `ResolvesIndexSorting` | any index accepting `?sort=`/`?direction=` | `$sort` reaches `orderBy()`, so the allow-list is a security boundary |
| `ReordersSiblings` | move-up / move-down actions | authorize-then-move, so the authorize cannot be forgotten |
| `ReparentsChildren` | the "move or delete" reassignment branch | `position` isn't reassigned on a parent change, and the FK isn't mass-assignable |
| `RedirectsAfterSave` | the Save / Save-and-stay redirect | the `status=saved` flash the edit page's confirmation depends on |

Each owns only the part that is genuinely identical. `RecordsManualRevisions` deliberately does
not absorb `$model->update()`, and `ReordersSiblings` does not absorb the response — Scene's move
actions also answer JSON. Read the concern's docblock before extending it.

Project-scoped queries for entities that reach `Project` through a parent (`Chapter` via its act,
`Scene` via chapter → act) come from `Project::chapterQuery()` / `Project::sceneQuery()`. They
return a **Builder, not a relation**, on purpose: a `hasManyThrough` joins `acts`, which also has
`name` and `position`, so `orderBy('position')` on it is an ambiguous-column error. Callers build
their own ordering and filtering on top.

## Blade & frontend

- Extract reusable UI into Blade components under `resources/views/components`
  (buttons, badges, table rows, icon links). Reuse an existing component before creating a new one.
- Keep presentation logic out of templates; prefer semantic HTML and ensure keyboard
  accessibility.
- **Icon-only controls compose `<x-icon-button>`**, which is the single home of their shape,
  colour variants (`outline` / `danger` / `light` / `ghost`) and accessible name. Never re-type
  those classes — that is how the delete button in a table ends up a different red from the one
  in a dialog. The seven wrappers (`icon-edit-link`, `icon-delete-button`, `icon-move-button`, …)
  are the examples to copy.
- An icon is never a label: every icon control carries **both** a `title` and an `sr-only` span.
  `<x-icon-button>` does this from its required `label`, so composing it cannot get it wrong.

> [!WARNING]
> **Never use a Blade directive as an attribute inside an `<x-…>` tag** — no `@disabled($flag)`,
> no `@if ($href) href="…" @endif`. It does not error: the component-tag compiler stops matching
> the tag and Blade prints `<x-icon-button …/>` on the page as **text**. Use a bound attribute
> instead (`:disabled="$flag"`, `:href="$href"`); the attribute bag already drops `false`/`null`
> and expands `true` to `disabled="disabled"`. Directives on a plain `<button>` are fine — it is
> only component tags that break. `BladeComponentCompilationTest` guards every page against this,
> and `IconButtonComponentTest` guards the components.
