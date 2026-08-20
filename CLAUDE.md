# Project guidance

Use these rules during planning and development. Prefer clear code that junior developers can maintain. Question a preference when a better design has a clear benefit.

## Commands

- PHP tests: `composer test`
- JavaScript tests: `npm run test`
- Format: `composer lint`
- Check formatting: `composer lint -- --test`
- Build frontend: `npm run build`
- Start development server: `make up` (Docker, the default); `php artisan serve` when a native run is asked for
- Run all checks: `bash scripts/verify.sh`
- Run one PHP test: `bash scripts/verify.sh --filter <pattern>`

PHP tests use separate in-memory SQLite databases. JavaScript tests use Vitest and stay next to their source files. Use the scripts in `scripts/` before you add another command sequence.

Docker is the default way to run the app and provides the same commands. See [Docker development](documentation/development/docker.md). The native server is for when someone asks for it by name.

Run the native server or the Docker stack, never both. They share port 8000 and `database/database.sqlite`, and two writers on one SQLite file corrupt it. Each start command refuses while the other holds the port.

`master` is protected. Ship changes through a branch and pull request. The `tests` check must pass before squash-merge.

## Architecture

- Follow Laravel conventions.
- Prefer small domain objects and composition over inheritance.
- Keep controllers, Blade templates, and Eloquent models thin.
- Reuse an existing project pattern before you create a new one.
- Do not add an abstraction until a second caller needs it.
- Explain and document technical debt.

Put logic in these locations:

- Input validation: Form Requests in `app/Http/Requests`; reusable rules in `app/Rules`.
- Authorization: policies in `app/Policies`.
- Reusable domain workflows: services or actions in `app/Services`.
- Model lifecycle invariants: `booted()` hooks.
- Constant and reference data: `app/Support` or `app/Enums`.

A controller action should resolve the model, authorize it, delegate the work, and return a response.

Read [.claude/conventions/tooling.md](.claude/conventions/tooling.md) before you run shell commands. Let the lockfile select the package manager, and do not mix shell syntax.

## Authorization and validation

- Validate input on the client and server as early as practical.
- Derive rules from the schema and domain. Centralize shared rules.
- Authorize every controller action that reads or writes a resource.
- Authorize child resources through their owning `Project` and `ProjectPolicy`.
- Mirror the policy check in the Form Request `authorize()` method.
- Do not treat route binding or hidden form fields as access control.
- Test that a non-owner receives a 403 response.

Global settings are the exception to the project ownership walk. They are singletons with no owning project and use the `access-admin` gate. See [rendering and public access](documentation/architecture/README.md#rendering-and-public-access).

## Testing

- Add a feature test for each endpoint, controller action, and bug fix.
- For a bug fix, add a test that fails before the fix.
- Use plain PHPUnit, `RefreshDatabase`, factories, `actingAs()`, and named routes.
- Cover the happy path, authorization, validation, and affected domain invariants.
- Never use the development database for verification.

For a small database probe, use `bash scripts/probe-test.sh '<php>'`. It creates a temporary feature test against in-memory SQLite and removes it after the run. If a probe must use seeded data, wrap it in a transaction and roll it back.

## Database

- Add indexes for known query patterns.
- Prefer readable Eloquent queries to raw SQL.
- Use transactions for multi-step writes when the database supports them.
- Eager-load relations used by a view.
- Keep index-page filtering, sorting, and search in the controller `index` method.

## Frontend

- Keep presentation logic out of Blade.
- Reuse components for common interface patterns.
- Prefer semantic HTML and keyboard-accessible controls.
- Resolve stored theme and font slugs only through `ThemePreset::resolve()` and `FontChoice::resolve()`.

See [interface documentation](documentation/interface/README.md), [themes](documentation/interface/themes.md), and [fonts](documentation/interface/fonts.md).

## Specifications and documentation

Feature specifications live in `.specs/`. Use the `mp-draft-spec` skill. Folder location and `status:` frontmatter must agree. See [.specs/README.md](.specs/README.md).

Start documentation work at [documentation/README.md](documentation/README.md). Then open only the guide for the feature you change.

- Documentation and agent prose: [.claude/rules/documentation.md](.claude/rules/documentation.md)
- Code comments: [.claude/rules/code-comments.md](.claude/rules/code-comments.md)
- Changelog entries: [.claude/rules/changelog.md](.claude/rules/changelog.md)

Use ASD-STE100 Simplified Technical English for code comments. Each commit body must explain why. Add one dated `CHANGELOG.md` section for each pull request.
