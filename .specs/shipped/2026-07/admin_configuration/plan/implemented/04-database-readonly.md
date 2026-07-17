# Task 04 — Database configuration (read-only)

## Scope

Turn the Database placeholder from task 01 into a **read-only** display of the app's active
database connection.

**Builds:**

- **Flesh out `DatabaseConfigurationController@edit`** to gather safe connection facts and pass
  them to the view. Read from config, not a hard-coded list:
  ```php
  $name   = config('database.default');               // e.g. 'sqlite'
  $config = config("database.connections.$name", []);
  ```
  Pass a **whitelisted** subset to the view — driver, and database name/path/host only.
  **Never** pass `password` (and prefer not to pass `username`). Do the whitelisting in the
  controller so the view can't accidentally dump the whole array.
- **Replace `admin/database/edit.blade.php`** (task-01 stub) with an `<x-admin-layout>` page:
  one card, heading "Database configuration", and a small definition list showing:
  - **Driver** (`$config['driver']`, e.g. `sqlite`, `mysql`),
  - **Database** — for `sqlite`, the `database` path; for server drivers, the `database` name,
  - **Host** — only when present (absent for sqlite).
  Purely factual display. **No** explanatory/CLI note (Q4), **no** forms, **no** action buttons.

## Explicitly NOT in this task

- Any switching of `DB_CONNECTION`, `.env` rewriting, migration, or SQLite⇄MySQL conversion →
  a **separate future spec**, and (per the design docs) that belongs in an Artisan command run
  with the site offline, never a controller action. Do not add any write route here.
- Any on-screen note *about* CLI/conversion being the future path (Q4 refinement — the page is
  purely the current connection).

## Depends on

- **Task 01** (admin group, `DatabaseConfigurationController`, `<x-admin-layout>`, sidebar).

## Key decisions already made (binding)

- Read-only display only (Q4).
- **Never render the DB password** (invariant 5) — enforced by whitelisting in the controller.
- No CLI/coming-soon note on this page (Q4 refinement).

## Consult

- `../expanded/ui.md` → *Section 4. Database configuration*.
- `../expanded/architecture.md` → *Section 4* (why conversion is out of scope — context only).

## Tests

- **Renders:** authenticated `GET admin.database.edit` → `200`, shows the current driver and
  database name/path (under the default sqlite test connection, assert the driver `sqlite`
  appears).
- **Password never leaks (the key test):** configure a fake server connection at runtime with a
  known password (e.g. set `database.connections.fake` with `'password' => 's3cr3t-do-not-show'`
  and `database.default` = `fake`, or override the mysql connection's password), hit the page,
  and assert the rendered HTML does **not** contain that password string. This guards
  invariant 5 directly.
- **Sidebar active state:** the Database sidebar link carries `aria-current="page"` here.
- **Authorization:** guest → login redirect.
