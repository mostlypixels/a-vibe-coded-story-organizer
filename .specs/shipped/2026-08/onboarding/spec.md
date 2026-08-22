---
status: shipped
shipped: 2026-08-22
planned: 2026-08-22
expanded: 2026-08-22
---

# Onboarding

## Problem

A new author is often not a technical person. After they register, they land on the
Welcome page and must create a project from nothing. The codex starts empty, so they
must invent every attribute themselves. Generic attributes also misfit the story: a
"CEO" makes no sense in an ancient Greece setting. Nothing guides the first project.

## Goals

- Guide a brand-new user (no projects yet) through their first project.
- Let the author pick a genre, then seed a fitting starter set so the project is not empty.
- Match the seed to the genre, so codex attributes fit the setting.
- Explain the core concepts in plain, friendly language as the author goes.
- Offer to install the Melusine demo projects from onboarding, as an opt-in step.
- Back the seed with an artisan command, so the CLI and the web flow do the same work.
- Let the author skip the guided steps and get a blank project at any point.
- After the seed, land on the new project and point at what onboarding made.

## Flow

- Show onboarding to a user with no projects. This is the empty state of the project list.
- If a user later deletes their last project, the empty state shows onboarding again.
- A Skip link is visible on every step. Skip makes an empty, Blank-genre project and ends
  the flow. Skip never seeds a bundle.
- After the seed, send the author to the new project home. Show a short, dismissible hint
  that points at the seeded codex, tags, and book skeleton.

Seed each bundle with:

- Codex attributes, per entry type, that fit the genre.
- A starter tag vocabulary.
- A few sample codex entries as deletable examples.
- A book with an act and chapter skeleton.

Genres in v1 (hardcoded bundles): Contemporary, Historical, Fantasy, Science Fiction,
and Blank. Blank seeds nothing. Store the chosen genre on the project.

## Onboarding copy

Friendly, human microcopy for the guided flow. Final wording, ready to place in the
Blade view (adjust for translation).

**Welcome**

> Hi! Let's make your first project. You can change anything here later.

**What's a project?**

> A project is your universe. It holds all the books in your series and one codex you
> share across them. The codex is where you keep your worldbuilding: the people, the
> places, and the groups in your story.

**What are attributes?**

> Attributes are the facts you track about a character, place, or group. A character has
> an age. A city has a ruler. A guild has a founding year. You pick which facts matter,
> and every character gets the same set, so you don't forget one.
>
> We'll fill in a starter set for you, based on your kind of story. A fantasy world and a
> spy thriller need different facts, so an ancient Greek hero won't get a job title. Edit
> them, delete the ones you don't want, or add your own.

**Pick your genre**

> What kind of story is this? Your answer sets up the attributes, tags, and a few example
> entries. Writing something that doesn't fit? Pick Blank and build it yourself.

**Install demo projects**

> Want to look around first? Install our sample project, The Roman of Melusine, and poke
> at a finished codex, timeline, and chapters. You can delete it whenever you like.

**After the seed (hint on the project home)**

> Here's your project. We added some starter attributes, tags, and a first book. Open the
> codex to see them, and change anything that doesn't fit.

## Non-goals

- No new codex entry types. The types stay Character, Location, and Organization. Genre
  tunes attributes, not types.
- No admin UI to edit bundles. The bundles are hardcoded in v1.
- No genre picker on the normal "New Project" form. The guided flow is first-run only;
  later projects use the plain form.
- No AI or generated content. The seed is fixed preset data.
- The demo projects no longer install on their own. `db:seed` seeds the admin user; the
  Melusine projects move behind the opt-in install step and the artisan command.

## Rough approach

- Extend the existing first-run onboarding page (`resources/views/onboarding.blade.php`).
  The user picks a genre and names the project, then the app seeds the bundle.
- Model a bundle as fixed preset data (an enum plus support classes, like `app/Enums`
  and `app/Support`). Reuse `CodexAttribute`, `CodexAttributeValue`, `AttributeTimeline`,
  tags, and the book/act/chapter models. Seeding must honor the codex rules in
  [codex.md](../../../documentation/features/codex.md): set attribute positions, use
  `AttributeTimeline` for temporal values, and keep the leading-anchor invariant.
- Add a `genre` column to `projects`.
- Default the seeded book to `BookLanguage::English`. No language picker in the flow. The
  author changes it on the book later.
- Make the genre picker keyboard-navigable and screen-reader friendly, per the frontend
  rules in `CLAUDE.md`.
- Put the seed work in one action or service in `app/Services`. The web flow and the
  artisan command both call it, so they never drift.
- Add an artisan command that wraps the action. Flags for the genre (story type) and the
  project name, plus the owning user. It creates the project and seeds the genre bundle.
- Keep the Melusine demo as its own install step. The onboarding button and a command
  (or a command flag) both run the existing Melusine seeders. In development you run the
  new command and, when you want it, install the demo, in place of a plain reseed.

## Open ends

- The exact attribute, tag, and sample-entry lists per genre. Content, authored later.
- Whether the stored genre does anything after seeding (re-suggest, filter the UI) or is
  just a label.
- Where the bundles live in code (an enum vs support classes) and how they map to models.
- The command surface: one command with a demo flag, or a separate demo-install command.
- What `make seed` and the docker workflow do now that the demo is opt-in.
- The Melusine content is temporary filler. The author will supply a real novel of their
  own for the demo project later. The install mechanism stays; only the content changes.
