---
status: shelved
---

# Custom Codex Types

The codex has three kinds of thing: Character, Location, Organization. A world with a hard
magic system needs Bloodline, Rite, Artefact, Oath, Order, Ship, Language. Tags cannot carry
it — a tag gives a thing no list of its own, no fields of its own and no place in the
navigation, and a Rite is not a Character with a label stuck on it.

The demo data already shows the strain. The Gardenia Girl character list holds `Cat:
Katharina`, `HORSE: Cajou` and `WS butler: Marcus Brunley?` — a writer typing a type into
the name field because there was no type field. See `.scratchpad/series-planner.md`.

## Goals

- A project defines its own entry types: name, plural name, route segment, icon.
- Each type gets its own list, its own navigation entry and its own attribute set.
- Character, Location and Organization ship as the starting set. She can rename them,
  reorder them, or stop using them.
- A type says whether its things have a lifespan, and what to call the two ends ("Founded"
  / "Dissolved", "Forged" / "Broken").
- Changing a type's name must not break a saved link or a bookmark.

## Non-goals

- No per-type templates, no required fields, no validation rules per type.
- No type hierarchy and no sub-types.
- No types shared across projects. That belongs with `move-between-projects` and the
  shared-vocabulary work behind it.
- No change to what an attribute is, or to the attribute timeline.

## Rough approach

- `CodexEntryType` is a PHP enum today, and everything reads it: route constraints through
  `routeKeys()`, navigation, search domains, the lifespan labels, breadcrumbs. The work is
  turning it into a project-owned table without every caller learning a new shape — keep
  the method names (`label()`, `pluralLabel()`, `routeKey()`, `inceptionLabel()`,
  `tracksLifespan()`) and swap what backs them.
- Seed the three existing cases per project in the migration, keyed on the current string
  values so stored `codex_entries.type` rows and existing URLs still resolve.
- Attribute definitions move from project-wide to type-wide. That is the second half of the
  change and the reason a chamber maid is offered fifteen boxes today.
- Open: deleting a type that still holds entries. Block, or move its entries to another
  type first?
- Open: `SearchDomain` has one domain per type. Does a project-defined type get its own
  search column, or do they all share one "codex" domain?
