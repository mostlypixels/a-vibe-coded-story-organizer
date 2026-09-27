---
status: shipped
shipped: 2026-09-27
planned: 2026-09-27
expanded: 2026-09-27
---

# Hover Link Menu

## Problem

Every top-level nav item with children is a button that only opens a dropdown. For example, to reach the Story page, the writer clicks "Story", then clicks "Story" again in the menu. The most common pages cost two clicks and a menu scan. Some menus repeat their own name as the first item to make up for it.

## Goals

- The rule applies to every top-level nav item with children. Today: Story, Timeline, Codex, Tools and the user menu.
- A click on the item goes to its own page.
- With a mouse, a hover on the item opens its submenu.
- Keyboard and touch can open the submenu without hover, through a separate chevron button next to the link.
- Remove a first item that repeats the parent's name and link.
- The user menu gets a new account landing page, its click target. For now it only has cards that link to Profile and Configuration, like `resources/views/tools/home.blade.php`.
  - Log Out stays only in the menu. It gets no card.
  - Every user sees the Configuration card. Some Configuration subpages are not admin-only.
- The active-page underline stays as it is.

## Non-goals

- The mobile (responsive) menu. It already lists every link with no dropdowns.
- The contents or order of the menus.
- The book switcher. It is a separate control, not a nav item, and stays as it is.
- Mega-menu or multi-level submenus.

## Rough approach

- Make one shared nav trigger: a link plus an `x-disclosure-button` chevron. Use it in `resources/views/components/navigation/project-menu.blade.php` and `resources/views/layouts/navigation.blade.php`.
- Reuse the Alpine `open` state in `x-dropdown`. Add hover open and close as an option, so other dropdowns do not change.
- Follow the disclosure pattern already in `documentation/interface/components.md`, which sets `aria-expanded` and `aria-controls` on the chevron.

## Open questions

- Does `layouts/error-navigation.blade.php` follow the same rule?
- Hover delays: how long to wait before the menu opens and closes, so the pointer can cross the gap without the menu closing.
- Does a hover-opened menu close when the chevron or a link gets focus?
- Does the chevron need its own hit area on narrow `sm` widths?
