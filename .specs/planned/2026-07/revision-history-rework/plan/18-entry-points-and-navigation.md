# Task 18 — Entry points and navigation

## Scope

Now that the entity history exists, point everything at it.

* **Edit pages**: the existing per-field *History* icon links to
  `revisions.index` + `?field=<field>` (same destination, new URL). Add one **entity-level
  History link** to `x-edit-actions`' Actions card, pointing at `revisions.index` with no
  filter — this becomes the primary entry point. Wire it for all seven revisionable edit
  screens (Project, Act, Chapter, Plotline, Event, Scene, Codex entry).
* **Revisions browser sidebar** (`resources/views/revisions/partials/sidebar.blade.php`,
  fed by `App\Services\ProjectRevisionsBrowser`): leaves keep their per-field counts but
  link to `revisions.index?field=`; the **entity name itself becomes a link** to the
  unfiltered entity history. Collapse / filter / count-badge behaviour is unchanged.
* **Active state**: `x-revisions-layout` passes the active `(entity, id, field)`; with
  `field` now optional, the entity-level page must still mark its entity's group open and
  its entity row `aria-current="page"`.
* Grep for any remaining `route('revisions.index', [... 'field' => ...])` three-argument
  call sites and update them (the route signature changed).

## Depends on

Tasks 13, 14.

## Key decisions already made

* The sidebar tree's **shape** does not change — only the URLs its leaves emit and the new
  entity-level link. Its bounding behaviour (count badges, default-collapsed groups,
  Alpine filter box) was deliberate and stays.
* The per-field History icon stays on edit pages: from a field, "what happened to *this*"
  is still the natural question — it just lands on the filtered entity page now.

## Consult

* `expanded/ui.md` — *4. Entry points elsewhere*.
* `documentation/architecture.md` → *Revisions browser (Tools ▸ Revisions)* — the
  behaviour that must survive.
* `app/View/Components/RevisionsLayout.php`, `resources/views/components/edit-actions.blade.php`.

## Tests

* Extend `tests/Feature/RevisionBrowserTest.php`: leaves link to `revisions.index?field=`;
  the entity name links to the unfiltered history; the sidebar still renders on the
  entity-level page with the right group open and `aria-current` set.
* One assertion per revisionable edit page (extend the existing per-entity feature tests,
  e.g. `SceneTest`, `ActTest`) that the Actions card exposes the entity History link
  pointing at the right route.
* Non-owner still gets 403 on the browser landing (existing test, keep green).
