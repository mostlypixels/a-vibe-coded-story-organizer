---
status: draft
---

# Codex attributes on demand

A new character form asks for a name, then fifteen empty boxes: Hair color, Skin color,
Eye color, Build, Height, Physical build, Gender, Religion, Race, Occupation, Priorities,
Secrets, Hobbies, Fears, Reputation. The writer has one fact — a name she just used in a
paragraph. The form reads as a to-do list she did not ask for, and it is the reason she
keeps her codex in a text file instead.

The edit form is the same shape, larger. A well-used entry runs five screens: sixteen
attribute sections, thirteen of them holding one empty "Starting value" box and an "Add
period at..." row. The three attributes she actually filled are buried among the thirteen
she never will.

The read pages that shipped recently already do the right thing — they show what is
filled. The forms did not follow.

## Goals

- A create form shows a name, a description, and nothing else by default.
- An edit form shows the attributes that have a value.
- One control adds an attribute to this entry, from the ones the project defines.
- The writer can tell that the project's attribute list is hers to change. Nothing on the
  form says so today.
- Removing the last value of an attribute removes it from the form again, without
  removing it from the project.

## Non-goals

- No change to what an attribute is, or to story-time attribute values and their
  timeline.
- No change to the project-level Attributes screen, which already works — she can delete
  "Skin color" and "Hobbies" there.
- No per-entry attribute definitions. An attribute stays a project-wide thing.
- No change to the codex entry read pages.
- Not a form redesign. Only which attributes are on it.

## Approach

- "Has a value" is the test for showing an attribute, matching what the read pages
  already decide. Confirm the two use one rule rather than two that drift.
- The add control lists the project's attributes for this entry type, minus the ones
  already shown.
- The default attribute set a new project gets is a separate lever, and a blunter one:
  fifteen attributes for every character is a choice made before the writer arrived.
  Out of scope here, but worth naming — see `onboarding-codex-data`.

## Open ends

- Whether an attribute with a value in an earlier story period, but none now, counts as
  filled. It has history, so hiding it loses the way back to that history.
- Whether the add control should also create a new project attribute inline, or only pick
  an existing one. Creating inline is what a writer mid-sentence wants and is also how a
  project ends up with fifteen attributes again.
- Whether an empty-but-pinned attribute is worth supporting for a writer who wants the
  prompt.
