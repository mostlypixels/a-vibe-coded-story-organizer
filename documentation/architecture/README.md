# Architecture

[Documentation](../README.md) › Architecture

Imagoldfish is a Laravel application for planning and writing books. It uses Blade, Tailwind CSS, Alpine.js, and small JavaScript modules.

## Domain model

```text
User
└── Project
    ├── Plotline
    ├── Event
    ├── CodexEntry
    ├── WordCountSnapshot
    └── Book
        └── Act
            └── Chapter
                └── Scene
```

- A project owns the shared world: timeline, plotlines, Codex, search, revisions, and writing history.
- A book owns the manuscript and publication settings.
- A project always has a main plotline, Start and End events, and at least one book.
- A book can have no name. `Book::displayName()` then returns the project name.

See the [glossary](glossary.md) for project terms.

## Authorization

Authorization flows through the owning `Project` and `App\Policies\ProjectPolicy`.

```php
$this->authorize('update', $scene->chapter->act->book->project);
```

- Controllers authorize every resource read or write.
- Form Requests repeat the same ownership check in `authorize()`.
- Route binding and hidden inputs are not access control.
- Tests cover the non-owner `403` case.
- `CrawlerSetting` is global. Any authenticated user can update it.

## Manuscript ordering and numbering

`Book`, `Act`, `Chapter`, and `Scene` store a sibling-scoped `position`.

- Model hooks assign `max(position) + 1` within the parent.
- Move actions swap adjacent positions.
- Seeders set positions explicitly because `WithoutModelEvents` disables hooks.

`App\Support\StoryNumbering` derives gap-free display numbers for one complete book.

> [!IMPORTANT]
> Build numbering from the complete book tree. A filtered or paginated tree produces incorrect numbers.

Static archive paths and imported `position` values use stored positions. They do not use display numbers.

## Routing

Routes use shallow resources.

- Manuscript creation routes nest under a book.
- Shared-world creation routes nest under a project.
- Edit, update, and delete routes bind the child directly.
- `App\Support\RouteContext` resolves the project and book for shallow routes.

## Rendering and public access

- `Scene::renderedContents` is the Markdown render path for the app, public scene links, and static reading exports.
- Public scene links use a random token and remain read-only.
- Rich HTML uses the rules in [Rich text](../features/rich-text.md).
- Global crawler visibility uses the dynamic `/robots.txt` route and `x-robots-meta`. The default is hidden.

## Navigation context

`App\Support\ProjectNavigation` and `RouteContext` own project, book, and active-section resolution.

- Route context wins over remembered account context.
- `TrackActiveProject` stores context only after a successful response.
- Page titles and breadcrumbs use route context.
- Blade templates render navigation state. They do not calculate it.

## Feature references

| Feature | Reference |
| --- | --- |
| Codex and temporal attributes | [Codex](../features/codex.md) |
| Autosave and history | [Revisions](../features/revisions.md) |
| Rich HTML and Markdown editing | [Rich text](../features/rich-text.md) |
| Counts, history, and goals | [Writing progress](../features/writing-progress.md) |
| Static archive | [Archive format](../export-import/archive-format.md) |
| EPUB | [EPUB](../export-import/epub.md) |
| Reusable views | [Components](../interface/components.md) |
| Runtime colors | [Themes](../interface/themes.md) |
| Runtime fonts | [Fonts](../interface/fonts.md) |

## Where logic lives

| Concern | Location |
| --- | --- |
| Input validation | `app/Http/Requests`, reusable rules in `app/Rules` |
| Authorization | `app/Policies/ProjectPolicy` |
| Lifecycle invariants | Model `booted()` hooks |
| Multi-step workflows | `app/Services` or an Action class |
| Reference data | `app/Support`, `app/Enums`, or configuration |
| Reusable interface | `resources/views/components` |
| Frontend behavior | `resources/js` |

## Related documentation

- [Documentation index](../README.md)
- [Glossary](glossary.md)
- [Development practices](../development/README.md)
