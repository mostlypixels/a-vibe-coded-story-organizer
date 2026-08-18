# Code style

[Documentation](../README.md) / [Development](README.md) / Code style

Write code that a new contributor can read and change safely. Match nearby code unless this guide states a stronger rule.

## Formatting and naming

- Use Laravel Pint. Run `composer lint` to format or `composer lint -- --test` to check.
- Follow Laravel conventions.
- Use descriptive names. Avoid unclear abbreviations.
- Keep manuscript titles independent from stored positions and calculated numbers.

## PHP

Use modern PHP when it improves clarity:

- arrow functions for short closures;
- `match` for clear value selection;
- backed enums for fixed value sets;
- constructor property promotion;
- typed properties, parameters, and return values;
- `Rule::enum()` and other first-class validation helpers.

## Comments

Add a comment only when it explains intent, a constraint, or a non-obvious decision. Do not repeat the code. Keep comments short and current.

```php
// The model hook assigns the next position within this chapter.
$scene = Scene::create($data);
```

## Controllers, requests, and models

Keep controllers to **resolve → authorize → delegate → respond**. Put validation in Form Requests. Models declare fillable fields, casts, typed relationships, and lifecycle invariants.

Before copying a controller pattern, check `app/Http/Controllers/Concerns`:

| Concern | Purpose |
|---|---|
| `RecordsManualRevisions` | Record revisions around a manual update. |
| `ResolvesIndexSorting` | Apply an allow-listed sort and direction. |
| `ReordersSiblings` | Authorize and move a sibling. |
| `ReparentsChildren` | Move children before deleting a parent. |
| `RedirectsAfterSave` | Select the save or save-and-stay response. |

Keep each concern limited to behavior that is identical for all callers. Read its docblock before extending it.

Use `Project::chapterQuery()` and `Project::sceneQuery()` for project-scoped lookups through parents. They return builders so callers can apply unambiguous ordering and filters.

See [best practices](best-practices.md#where-logic-lives) for responsibility boundaries.

## Blade and frontend code

- Reuse components from `resources/views/components`.
- Keep business logic out of templates.
- Use semantic HTML and keyboard-accessible controls.
- Build icon-only controls with `<x-icon-button>` or its wrappers. The component supplies a consistent style, `title`, and screen-reader label.
- Pass conditions to Blade components with bound attributes such as `:disabled="$flag"`.

> [!WARNING]
> Do not place Blade directives such as `@disabled` or `@if` inside an `<x-…>` tag. The component compiler can print the tag as text. Directives remain valid on plain HTML elements.
