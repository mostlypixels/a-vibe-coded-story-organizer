# Task 3 — `HasRevisions` trait + `AutosavableFields` registry

## Scope

The declarative wiring layer, with no write logic yet:

* `App\Models\Concerns\HasRevisions` trait — `revisions(): MorphMany` relation, and the
  `abstract revisionProject(): Project` contract.
* Implement `revisionProject()` on every registered model: `Act`, `Event`,
  `CodexEntry`, `Plotline` (`return $this->project;`), `Chapter`
  (`return $this->act->project;`), `Scene` (`return $this->chapter->act->project;`),
  `Project` (`return $this;`). Add `use HasRevisions;` to each.
* `App\Support\AutosavableFields` — the registry class: `FieldKind` enum (`Rich |
  Markdown | Plain`), the `REGISTRY` constant (slug => [model class, [field =>
  FieldKind]]), and the lookup methods (`slugs()`, `modelFor()`, `kindOf()`,
  `windowSeconds()`, `characterCap()`, `validationRule()`).

Does **not** include: the `Revision` write path (`RevisionRecorder`, task 4), the HTTP
controller/routes (task 6), or any Blade/JS. `validationRule()` should return real rule
arrays (reusing `ValidMarkdown`, the `SanitizeHtml` rule, and `max:` from
`characterCap()`) even though nothing calls it yet — task 6 is what wires it into the
controller, but this task is where correctness of the rule-building is tested.

## Depends on

Nothing new (models/relations already exist; this task only adds a trait and a new
Support class, per task 1's enum/config already existing).

## Key decisions already made

* **Slug vocabulary**: `project`, `act`, `chapter`, `plotline`, `event`, `scene`,
  `codex` — mirrors the app's own URL segments (`/codex/{codexEntry}/edit` already uses
  `codex`, confirmed against `routes/web.php`). `{entity}`, not `{type}` — the app
  already uses `{type}` for `CodexEntryType` one segment over
  (`/projects/{project}/codex/{type}`) and a second meaning would collide conceptually.
* **The 14-field-by-kind table** is exactly `handoff.md` §7 / `expanded/architecture.md`'s
  registry block — copy it verbatim, do not add or drop a field.
* `RichTextFields::FIELDS` already lists every `Rich`-kind field this registry needs
  (confirmed this session) — `AutosavableFields` does not duplicate that list, it wraps
  it. For `Rich` kind, `validationRule()` should delegate to the same `SanitizeHtml`
  rule + `RichTextFields` allow-list the existing Form Requests use, not a parallel
  implementation.
* `Markdown` kind uses `ValidMarkdown` (existing rule, read this session) plus a
  `max:` from `characterCap()`. `Plain` kind is a bare `string|max:` (this is the new
  kind this feature introduces — `Project.rights` is a raw `<textarea>` today, not
  `x-wysiwyg`).
* `windowSeconds()`/`characterCap()` read `config('revisions.windows'/'caps')` keyed
  `Model.field` (short class basename, e.g. `Scene.contents`), falling back to
  `'default'` — never hard-code a per-field number here; task 1's config file is the
  only source.

## Consult

* `expanded/architecture.md` — the `AutosavableFields` code sketch and the
  `HasRevisions` trait sketch.
* `expanded/data-model.md` — the project-resolution table (model → path).
* `app/Models/Concerns/HasSiblingPosition.php` and `SanitizesRichHtml.php` (already
  read this session) — match their doc-comment style and the "one trait, one
  responsibility" shape.
* `app/Support/RichTextFields.php` — the sibling Support class this one must not
  duplicate.

## Tests

* `AutosavableFields::slugs()` returns exactly the 7 expected slugs.
* `AutosavableFields::modelFor()`/`kindOf()` for every one of the 14 registered
  `Model.field` pairs returns the documented model class and kind.
* `revisionProject()` on each of the 7 models, given a real factory-built object nested
  under a project, returns that project — one assertion per model, this is the
  authorization boundary and deserves direct coverage independent of any HTTP test.
* `validationRule()` for a `Rich` field includes the `SanitizeHtml`-equivalent rule; for
  `Markdown` includes `ValidMarkdown`; for `Plain` is a bare string rule with the
  registry's character cap as `max:`.
