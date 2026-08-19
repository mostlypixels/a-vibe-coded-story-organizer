# EPUB

[Documentation](../README.md) › [Export and import](README.md) › EPUB

`App\Services\EpubExporter` creates one EPUB for one book. It accepts a `Book`, returns a temporary file path, and cleans up after an exception. It has no HTTP dependency.

`rampmaster/phpepub` builds the package structure. The exporter owns the XHTML, metadata values, CSS, and navigation shape. Blade views under `resources/views/exports/epub` produce all content documents.

## Book boundary

All publication data comes from the selected book:

- display name and language;
- author, publisher, ISBN, and rights;
- matter pages and cover;
- `PublicationSetting`;
- manuscript tree.

The generated identifier is `urn:imagoldfish:book:{id}`. Authorization uses `$book->project`. “Nothing to export” applies only to the selected book.

The download filename uses the book's display name. Reserve `$book` for the model and `$epub` for the library package object.

## Content isolation

Two render paths must remain separate.

1. Scene bodies and matter Markdown use the exporter’s private SmartPunct CommonMark converter. They do not use `Scene::renderedContents`.
2. Rich descriptions use `RichText::toXhtmlFragment()` before Blade emits them.

Markdown does not use the XHTML helper. Rich HTML does not use the Markdown converter.

## Package validation

`validatePackage()` runs during every export.

- Every XHTML file must parse as XML.
- The OPF must validate against the vendored EPUB 3 RelaxNG schema.
- A validation failure is a generator defect and throws `RuntimeException`.
- `EpubExportException` is only for a book with no scenes.

The gate does not judge labels, navigation meaning, or reading quality. Tests must open the package and assert those details.

Imported rows can bypass Form Requests and contain unusual values. Keep export-side fallbacks for valid but meaningless output, such as an empty navigation label.

## Complete outline and numbering

The exporter loads the complete book tree and builds one `StoryNumbering` map.

- Keep empty chapters as heading-only pages.
- Keep empty acts as divider pages.
- Refuse the export only when the book has no scene anywhere.
- Use derived numbers for act and chapter headings and labels.
- Keep scene navigation labels scoped to the chapter.
- Pass the derived number as the `number` view key. Do not pass a stored `position` as a display number.

This keeps application and export numbering equal.

## Publication settings

`PublicationSetting` is one optional, unique row per book. It is not created by a model hook or backfill. `Book::publicationSettingOrDefault()` returns an unsaved default when no row exists. Resolve it once per export.

- Formatting choices live on enums.
- New optional rendering defaults off.
- Existing metadata and book-cover defaults remain on.
- Lazy and explicit default settings must produce content-identical packages.
- Keep lazy defaults and `PublicationSettingFactory::definition()` equal field for field.
- The regression test normalizes only library-generated OPF timestamps before comparison.

`UpdatePublicationSettingRequest::configRules()` is shared by the form and archive importer.

Read and export actions authorize `view` on `$book->project`; configuration writes authorize `update`. Routes bind `{book}`, so links pass a book identifier. `SECTION_KEYS`, `PINNED_FIRST_SECTION`, and the move helpers own section membership and pinning.

## Section order

`PublicationSetting::section_order` controls the spine sequence.

| Key | Content |
| --- | --- |
| `title` | Required title page; pinned first |
| `dedication` | Optional Markdown matter |
| `acknowledgements` | Optional Markdown matter |
| `preface` | Optional Markdown matter |
| `toc` | In-book table of contents |
| `body` | Manuscript |
| `appendix` | Optional Codex appendix |
| `postface` | Optional Markdown matter |

Matter renders only when its toggle is on and its content is not empty.

## Navigation depth

The in-book table of contents and EPUB navigation use the same depth.

### Chapters

The default is Act → Chapter. Acts and chapters use separate spine pages.

### Scenes

Scene entries link to `chapter-{id}.xhtml#scene-{id}`. Chapter pages emit anchors even for untitled scenes. Emit these anchors only at Scenes depth so the default output remains unchanged. In-page entries do not create new spine pages.

### Acts

`rampmaster/phpepub` couples a content page to a navigation point. It cannot place standalone chapter pages in the spine without adding chapter navigation entries.

At Acts depth, render one combined spine page per act. It contains the act divider and all chapters. Do not package standalone chapter pages at this depth.

Combined and standalone pages share `partials/act-body.blade.php`, `partials/chapter-body.blade.php`, and the matching view-data helpers. Keep the two render paths aligned.

## Chapter covers

A chapter cover renders when:

1. `include_chapter_covers` is on;
2. the chapter has a cover path; and
3. the file exists.

The cover is a navigation sibling immediately before the chapter. At Acts depth it is a root entry before the combined act page. Add it with the generic file API under `images/chapter-cover-{id}-{basename}`; `setCoverImage()` is reserved for the book cover.

## Codex appendix

The appendix renders only when:

1. the appendix toggle is on;
2. at least one entry type is selected; and
3. the selected book references matching entries.

> [!IMPORTANT]
> Filter entries through scenes in the selected book before applying the type filter. The Codex is project-scoped, and an unfiltered appendix can expose another book’s characters.

Entries sort by type and name. When images are enabled, embed the first media row that is an image and has bytes on disk. Missing files and non-image media are skipped.

`scene_codex_entry` is derived data. Factory-built scenes do not populate it; appendix tests must attach references or run the matcher. Do not load media when images are off. Multiple appendix images and a Review entity are outside the current feature.

## Blade output

Views under `resources/views/exports/epub` emit XHTML. Preserve these rules:

- XML declaration before the document type;
- self-closed void elements;
- `lang` and `xml:lang` on the root;
- trusted XHTML only from exporter preparation paths;
- scene anchors independent of scene titles;
- well-formed divider snippets.

## Maintenance boundary

The exporter currently filters data, renders pages, packages files, normalizes the OPF, validates the result, and creates filenames. Do not split packaging or validation out without reviewing the full workflow and its tests. The current size is not an endorsement of the design.

## Related documentation

- [Architecture](../architecture/README.md)
- [Archive format](archive-format.md)
- [Rich text](../features/rich-text.md)
