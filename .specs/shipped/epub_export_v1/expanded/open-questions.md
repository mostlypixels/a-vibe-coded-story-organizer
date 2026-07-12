# Epub export (v1) — open questions

Sharp, answerable prompts for the `plan-tasks` grill. Each has a recommended answer from this
expansion; the grill should confirm or override before decomposition.

1. **Is `grandt/phpepub` actually the right composer package?** Not verified live during this
   expansion (no Packagist/registry check performed). *Recommendation:* verify it's actively
   maintained and PHP 8.x-compatible before the plan locks it in; if stale, pick the best
   current alternative — the requirement is "a maintained library handles OPF/NCX/zip
   conformance," not this specific package name.

2. **Where does the "nothing to export" check live — Form Request or service exception?**
   `EpubExportRequest::rules()` can't easily express "the project's filtered act/chapter tree
   is non-empty" (that requires loading and filtering the tree, not just validating scalar
   input). *Recommendation:* let `EpubExporter::export()` throw a dedicated exception when the
   filtered tree is empty, caught by the controller and turned into a redirect-back-with-error
   (same user-facing effect as a validation failure, but implemented where the logic actually
   lives) rather than forcing the Form Request to duplicate tree-loading logic.

3. **Where does the Project edit form currently live, and how does a 6-field "Book metadata"
   addition fit its existing layout?** Not directly explored during this grill — only
   `UpdateProjectRequest` and the `Project` model were inspected, not the edit Blade view.
   *Recommendation:* locate it first in the plan stage; likely a new fieldset/section rather
   than a new page, consistent with `name`/`description` already being edited in place.

4. **Should the epub and zip export forms share one `project_id` <select>, or stay fully
   independent forms?** Two separate `<form>` posts to two different routes need two project
   pickers unless UI state is shared via Alpine. *Recommendation:* keep them fully independent
   (simpler, no new Alpine state) unless the plan stage's UI pass finds the duplication
   genuinely awkular on the page.

5. **`ProjectCoverService` vs. inline `ProjectController` methods for cover image
   handling?** `data-model.md`/`architecture.md` intentionally left this open — a single
   nullable path column is much thinner than the Codex media system it borrows validation
   from. *Recommendation:* start with private `ProjectController` methods (per CLAUDE.md's
   "no abstraction before a second caller" rule); promote to a service only if epub-related
   code (or a future feature) needs to touch cover storage from somewhere other than the
   controller.

6. **Bundled EPUB XSD/RelaxNG schema files — licensing for redistribution in this repo?**
   `architecture.md`'s schema-validation step needs the actual IDPF/W3C schema files vendored
   somewhere (e.g. `resources/epub-schemas/`). *Recommendation:* confirm their license permits
   redistribution before committing them to the repo; if not, consider whether the packaging
   library (question 1) already bundles/exposes them, avoiding a second copy.

7. **`isbn` column: normalize storage (strip hyphens) or store exactly as typed?**
   `data-model.md` says "don't normalize away user formatting," but that means the OPF
   `dc:identifier` output must handle both `9783161484100` and `978-3-16-148410-0` consistently
   at render time rather than at write time. *Recommendation:* store as typed, format
   consistently (e.g. always hyphenated per ISBN convention) only when emitting the OPF, since
   validation (checksum) already strips punctuation to check correctness regardless of stored
   form.

8. **Common `language` values as a `<select>` vs. free-text input on the Project edit form?**
   `ui.md` flagged this without deciding. *Recommendation:* a `<select>` of common BCP-47 codes
   (en, en-US, en-GB, fr, de, es, …) with the stored value always being a plain string column
   (no enum) — avoids a big country-code enum while still guiding most users away from typos.
