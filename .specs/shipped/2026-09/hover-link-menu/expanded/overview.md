# Overview

## Scope

| Nav item | Click goes to | Menu keeps |
|---|---|---|
| Story | `books.story.home` | Overview, Acts, Chapters, Scenes |
| Timeline | `projects.timeline.home` | Plotlines, Events |
| Codex | `projects.codex.home` | entry types, Attributes, Tags |
| Tools | `projects.tools.home` | Revisions, Progress |
| User menu (the user's name) | new `account` page | Profile, Configuration, Log Out |

- Desktop only (`sm:` and up). The responsive menu in `layouts/navigation.blade.php` does not change.
- The book switcher, Dashboard and Search do not change.

## User stories

- As a writer, I click "Story" once and land on the Story page.
- As a mouse user, I move the pointer over "Codex", see its menu, and click "Characters".
- As a keyboard user, I tab to the chevron next to "Codex", press Enter, and reach the menu items.
- As a touch user, I tap "Story" and go to the page. I tap the chevron to open the menu.

## Acceptance criteria

- Each item in the table renders an `<a>` to its target and a separate chevron `<button>`.
- The chevron carries `aria-expanded` and `aria-controls` (`x-disclosure-button`).
- The `<a>` has `aria-current="page"` on its own hub page only.
- The active underline shows on the whole section, as it does now (`storyActive` etc.).
- A mouse hover opens the menu. The menu stays open while the pointer crosses from the item to the panel.
- A touch tap on the link does not open the menu. It only navigates.
- Escape closes the menu and moves focus back to the chevron.
- The menus have no item that repeats the parent: no "Story" in Story, and no "Timeline", "Codex" or "Tools" in their own menus.
- `/account` shows a heading and two cards: Profile (`profile.edit`) and Configuration (`admin.index`). Every logged-in user sees both.
- Log Out stays in the menu. It is not on the account page.
