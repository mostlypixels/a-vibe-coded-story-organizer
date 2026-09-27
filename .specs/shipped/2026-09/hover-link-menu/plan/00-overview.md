# Hover link menu — plan overview

The manual for this feature's tasks. Never implemented, never moved.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 1 | `01-dropdown-behaviour.md` | `x-dropdown`: Escape for every dropdown, one open menu at a time, opt-in `hover` mode. |
| 2 | `02-section-links.md` | `x-navigation.menu-link`. Story, Timeline, Codex and Tools become links plus a chevron. Repeated first items go. |
| 3 | `03-account-page.md` | `/account` page with two cards. The user menu in both navs links to it. |

Behaviour before markup: task 02 needs the `hover` mode and the link-safe trigger from task 01.

## Binding decisions

Settled in the grill. Do not re-litigate.

- **Hover timing:** open at once on pointer enter. Close 250 ms after the pointer leaves the trigger and the panel.
- **Hover is mouse only:** act only when `pointerType === 'mouse'`. A touch tap on the link navigates and never opens the menu.
- **Focus does not open a menu.** Only the chevron opens it from the keyboard (APG "Disclosure Navigation Menu with Top-Level Links").
- **One menu open at a time.** Opening any dropdown, by hover or click, closes the others.
- **Escape closes every `x-dropdown`** and returns focus to its disclosure button.
- **Chevron names:** "Story menu", "Timeline menu", "Codex menu", "Tools menu", "Account menu".
- **Account page:** title "Account", URL `/account`, route name `account`. Cards: Profile and Configuration. Every user sees both. Log Out stays only in the menu.
- **Error pages:** `error-navigation.blade.php` uses the same user-menu component.
- **Out of scope:** the book switcher, the responsive (mobile) menu, Dashboard, Search.

## Invariants

- `App\Support\ProjectNavigation` computes every active flag. Blade only reads them.
- The section underline (`storyActive` etc.) behaves as it does now.
- `aria-current="page"` marks only the page itself: the hub link on its hub page, a menu item on its own page.
- Each disclosure button keeps `aria-expanded` and `aria-controls` (`x-disclosure-button`).
- Non-hover dropdowns keep their click behaviour.
