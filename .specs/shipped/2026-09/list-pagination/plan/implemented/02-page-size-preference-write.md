# 02 — Page size preference write

## Scope

- Route `Route::patch('/preferences/page-size', ...)->name('preferences.page-size.update')`
  in the `['auth', TrackActiveProject::class]` group beside the profile routes
  (`routes/web.php` ~line 80). **Not** under the `admin.` prefix — this is set
  from a list, not from Configuration.
- `app/Http/Requests/UpdatePageSizeRequest.php`. `authorize()` returns
  `$this->user() !== null`, mirroring `UpdateAppearanceRequest`. Rule:
  `page_size => ['required', Rule::in(PageSize::sizes())]`.
- `app/Http/Controllers/PageSizeController.php@update`: write the column on the
  acting user, then redirect to the previous URL with `page` stripped.

## Not in scope

- No bar, no select, no view changes — task 03.

## Key decisions

- **No `return_to` field and no same-origin guard.** The redirect target comes
  from `url()->previous()`, which the session holds, so there is no user input to
  validate. Strip only the `page` query parameter so the writer lands on page 1
  of the same sorted, filtered list. This replaces the `return_to` design in
  `expanded/architecture.md` → Writing the preference; read that section for the
  rest, ignore its `return_to` paragraphs.
- No policy and no `ProjectPolicy` walk. The preference has no owning project and
  the write always targets the acting user.

## Consult

- `expanded/architecture.md` → Writing the preference
- `app/Http/Requests/UpdateAppearanceRequest.php` for the `authorize()` shape

## Tests

`tests/Feature/PageSizePreferenceTest.php`:

- PATCH with 250 stores 250 and redirects to the list the request came from.
- A previous URL carrying `?page=12` redirects to the same URL without `page`.
- 999 fails validation; the column is unchanged.
- A guest is redirected to login.
- User A's write does not touch user B's column.
