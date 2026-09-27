# UI and architecture

## Components

- **New `x-navigation.menu-link`** replaces `x-navigation.dropdown-trigger` (delete the old one).
  - Props: `href`, `active` (section underline), `current` (hub page → `aria-current="page"`).
  - Renders the label `<a>` plus an `x-disclosure-button` that holds the chevron icon.
  - The chevron button gets an accessible name, e.g. "Story menu". An icon-only button has no name otherwise.
  - Hit area: the full nav height (`h-12`) and at least 24px wide (WCAG 2.5.8).
- **`x-dropdown` gets a `hover` flag** (default `false`). Other dropdowns do not change.
  - The trigger wrapper `@click="open = ! open"` must not fire for the link. In hover mode, only the chevron toggles the menu. Otherwise a click on the link opens the menu while the page leaves.
  - Hover uses `@pointerenter` / `@pointerleave` on the whole wrapper (trigger + panel), and only when `$event.pointerType === 'mouse'`. A touch tap fires pointer events too, and must not open the menu.
  - Close after a short delay, so the pointer can cross the gap. The timings are in `open-questions.md`.
  - Add `@keydown.escape` → close and focus the chevron. The dropdown has no Escape handling now, so every dropdown gets it.
- Keep the Alpine logic inline in `dropdown.blade.php` while it stays a few lines. Move it to `resources/js/` as `Alpine.data` with a Vitest file if it grows past that.

## Views

- `resources/views/components/navigation/project-menu.blade.php`: four `x-dropdown hover` blocks use `x-navigation.menu-link`. Remove the repeated first items.
- `resources/views/layouts/navigation.blade.php`: the user menu uses `x-navigation.menu-link` to `account`.
- `resources/views/layouts/error-navigation.blade.php`: same user-menu change (see `open-questions.md`).
- `documentation/interface/components.md`: add `x-navigation.menu-link` and the `hover` flag.

## Active state

- `App\Support\ProjectNavigation` already has `storyHomeActive`, `timelineHomeActive`, `codexHomeActive` and `toolsHomeActive`. They become the link's `current`.
- Add `accountActive`: `routeIs('account', 'profile.*', 'admin.*')`. It is the user menu's underline, and `account` alone is its `current`.
- The Blade templates only read these flags. They do not compute them.

## Account page

- Route: `GET /account` → `AccountController@home`, name `account`, in the `auth` + `TrackActiveProject` group in `routes/web.php`, next to `profile.*`.
- No policy: the page shows only links. Profile and Configuration have their own checks (`access-admin` allows every user).
- View `resources/views/account/home.blade.php`, a copy of the `tools/home.blade.php` pattern: `x-page-heading` and two `x-card`s with a short text and a footer link.
- `App\Support\Breadcrumbs` gives no trail on off-project pages, so the page uses its own heading. That is correct.
- The page title comes from the existing `PageTitle` rules. Check that `account` gets "Account".

> [!WARNING]
> Space at `sm` (640px): the chevrons make each item wider. Check that the nav does not wrap or scroll sideways at 640px.
