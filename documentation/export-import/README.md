# Export and import

[Documentation](../README.md) › Export and import

| Page | Purpose |
| --- | --- |
| [Archive format](archive-format.md) | Contract for the lossless `data/` layer and readable `books/` layer |
| [EPUB](epub.md) | Rules for generating one publication-ready book |

Treat both documents as compatibility contracts. Change them with their tests.

An unfinished import keeps its ZIP and extracted folder under `storage/app/private/imports` so the writer can resume it. The daily `imports:purge` removes them, and the import row, after `import.purge_after_days` (7 by default). It also removes old files that no import row owns.
