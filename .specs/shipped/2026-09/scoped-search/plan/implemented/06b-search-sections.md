# 06b — One definition of the result sections

## Scope

- `App\Enums\SearchSection` (`Timeline`, `Story`, `Codex`), with a label and the domains it
  holds.
- `SearchDomain::section()` naming the section a domain belongs to.
- `SearchResults::has*Matches()` read the enum instead of their own domain lists.
- `components/search/narrow-panel.blade.php` drops its `$domainGroups` `@php` block and
  reads the enum.
- Not in scope: hiding sections (task 07), which is what needs this.

## Depends on

06.

## Key decisions

- The grouping is written twice today and task 07 needs it a third time. Three copies of a
  list that nothing checks is how it drifts — the same reason `carriesBook()` exists rather
  than a literal list of manuscript domains.
- The enum owns both directions: a section's domains, and a domain's section. Task 07 asks
  "is every domain in this section hidden", which needs the first.
- **No behaviour change.** Section headings, order and empty-section skipping stay exactly
  as they render today. This task is green-to-green.
- The `@php` block goes with it. Presentation logic does not live in Blade.

## Consult

`app/Support/SearchResults.php` for the current grouping and its docblock.

## Tests

- `SearchDomain::section()` covers all eight domains; every section's domain list is
  non-empty and the union is all eight.
- Existing search view and `SearchResults` tests still pass untouched — that is the proof
  nothing moved.
