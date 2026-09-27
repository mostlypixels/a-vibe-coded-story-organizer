# 03 — Account page

## Scope

- Route `GET /account` → `AccountController@home`, name `account`, in the `auth` + `TrackActiveProject` group next to `profile.*`.
- View `resources/views/account/home.blade.php`: `x-page-heading` "Account" and two `x-card`s, Profile (`profile.edit`) and Configuration (`admin.index`), in the `tools/home.blade.php` pattern.
- `ProjectNavigation::$accountActive`: `routeIs('account', 'profile.*', 'admin.*')`.
- User menu in `layouts/navigation.blade.php` and `layouts/error-navigation.blade.php`: `x-dropdown hover` with `menu-link` to `account`, the user name as the label, chevron "Account menu". The menu keeps Profile, Configuration, Log Out.
- Delete `x-navigation.dropdown-trigger` if task 02 left it.
- Deferred: nothing.

## Depends on

01, 02.

## Key decisions

- No policy: the page holds only links, and each target checks itself. `access-admin` allows every user.
- No Log Out card.
- No breadcrumb: `App\Support\Breadcrumbs` gives no trail on off-project pages.
- Check that `PageTitle` gives the page the title "Account".

## Docs

- `expanded/ui.md` → Account page, Active state.

## Tests

- Guest on `/account` → redirect to login. Logged-in user → 200, links to `profile.edit` and `admin.index`, no logout form.
- The user menu renders an `<a>` to `account`. It is underlined on `account`, `profile.edit` and `admin.settings.edit`, and has `aria-current` only on `account`.
- The error-page nav renders the same link.
- `DisclosureToggleTest`: both navs still name their panels.
- Browser (Firefox and Chromium): a click on the user name opens `/account`. Hover opens the menu.
