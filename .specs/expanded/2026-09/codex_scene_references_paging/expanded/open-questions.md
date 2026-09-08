# Open questions

1. **Reuse `config('search.cap')`, or a new `config/references.php`?**
   Recommend reuse. One inline cap, one number. A second key drifts and nobody notices.

2. **The scene direction can page in SQL; the codex direction cannot. Ship the asymmetry?**
   Recommend yes, commented. Forcing both through a hand-built paginator would load every
   referenced entry to page five of them.

3. **Does `scenes/show.blade.php` get the same treatment?**
   Recommend yes. The spec missed it; leaving it uncapped keeps the defect alive on the
   read page.

4. **Should the codex full page keep the entry's timeline ordering, or offer sortable
   columns?**
   Recommend timeline order only. Sortable headers mean re-sorting a PHP collection per
   request, and story order is the reason this list exists.

5. **Is the `book.position` fix in this feature, or its own?**
   Recommend this one. The fix is two lines and the full page makes the defect visible.

6. **`ReferencingScenes::forScene()` — worth extracting for two callers that are one line?**
   Recommend yes: it becomes four callers with the two new pages, and the eager-load list
   is the part that drifts.
