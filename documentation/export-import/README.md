# Export and import

[Documentation](../README.md) › Export and import

| Page | Purpose |
| --- | --- |
| [Archive format](archive-format.md) | Contract for the lossless `data/` layer and readable `books/` layer |
| [EPUB](epub.md) | Rules for generating one publication-ready book |

Treat both documents as compatibility contracts. Change them with their tests.

An unfinished import keeps its ZIP and extracted folder under `storage/app/private/imports` so the writer can resume it. The daily `imports:purge` removes them, and the import row, after `import.purge_after_days` (7 by default). It also removes old files that no import row owns.

The purge keeps the partial project, because the writer can have edited it. `projects.import_unfinished` marks it, so the project page shows an "import did not finish" note. The flag is set when the project phase creates the project and cleared when the import completes. The writer can also dismiss the note.
