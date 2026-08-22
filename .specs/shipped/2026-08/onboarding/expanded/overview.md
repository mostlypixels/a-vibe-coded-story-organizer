# Onboarding — overview

## Problem

A new writer lands on `onboarding` (project list redirects there when empty) and sees one
"New Project" button. The project starts with an empty codex, so the writer must invent
every attribute alone. Generic attributes also misfit the setting (a "CEO" in ancient
Greece).

## Goals

- Guide a writer with no projects through their first one.
- Pick a genre, then seed a fitting starter set: codex attributes, tags, sample entries,
  and an act/chapter skeleton on the auto-created book.
- Store the genre on the project.
- Offer an opt-in install of the demo project (Melusine) from onboarding.
- One shared seed action behind both the web flow and a new artisan command.
- Let the writer skip to a blank project at any step.
- After the seed, land on the project home with a dismissible hint.

## Non-goals

- No new codex entry types. Genre tunes attributes, not `CodexEntryType`.
- No admin UI for bundles. Hardcoded in v1.
- No genre field on the normal `projects.create` form. Guided flow is first-run only.
- No AI or generated content.
- No book-language picker. The seeded book defaults to `BookLanguage::English`.
- `db:seed` no longer auto-installs Melusine. It seeds the admin user only.

## User stories

- As a new writer, I pick "Fantasy" and get a project whose Characters already have a
  "Magic affinity" attribute and a few example entries I can edit or delete.
- As a new writer, I click Skip and get an empty, Blank-genre project.
- As a new writer, I install the demo to explore a finished codex and timeline.
- As a developer, I run one artisan command to create a seeded project, and another
  (or a flag) to install the demo, in place of a plain reseed.

## Acceptance criteria

- Onboarding shows a genre choice, a project-name field, a demo-install action, and Skip.
- Choosing a genre + name creates a project with `genre` set and the bundle applied.
- Blank genre (and Skip) create a project with no bundle content.
- Every seeded attribute value has a Start baseline (leading-anchor invariant).
- Seeded scenes, if any, resolve codex references (matcher runs last).
- After seeding, the writer is on `projects.show` and sees the hint once.
- A non-owner cannot trigger a seed or demo install for another user.
- `php artisan db:seed` creates the admin user and no demo projects.
