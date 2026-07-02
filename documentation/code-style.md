# Code style

The goal is code a junior developer can read, extend, and trust. When in doubt, match the
surrounding code.

## Formatting

- **Laravel Pint** is the formatter. Run `vendor/bin/pint` before committing; it applies the
  Laravel preset. Do not hand-fight its output.
- Follow standard Laravel conventions unless there is a compelling architectural reason not to.

## Naming

- Variables, methods, and classes are descriptive and meaningful. **Avoid abbreviations.**
- Titles for acts/chapters/scenes are freeform and never encode their number — the `position`
  is the number (see [architecture](architecture.md#act--chapter--scene-ordering)).

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

## Blade & frontend

- Extract reusable UI into Blade components under `resources/views/components`
  (buttons, badges, table rows, icon links). Reuse an existing component before creating a new one.
- Keep presentation logic out of templates; prefer semantic HTML and ensure keyboard
  accessibility.
