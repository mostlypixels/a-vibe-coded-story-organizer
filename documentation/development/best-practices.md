# Best practices

[Documentation](../README.md) / [Development](README.md) / Best practices

Follow the existing Laravel structure. Keep domain rules in one place and test each rule at its boundary.

## Where logic lives

- **Controllers** resolve input, authorize, delegate, and return a response.
- **Form Requests** authorize and validate HTTP input.
- **Models** define relationships, casts, and lifecycle invariants.
- **Services** hold reusable, multi-step workflows.
- **Rules** hold reusable validation logic.
- **Blade components** hold reusable presentation.

Do not create a service for one caller without a clear need. A private controller method is enough until another caller needs the same workflow.

Use model hooks for invariants, not application workflows. Seeders can disable model events. If a seeder must enforce an invariant, put the operation in a callable service and use it from both paths.

If HTTP and non-HTTP code share validation rules, expose a static rule method on the Form Request. Use the established `configRules()` name where it applies. Do not declare a static `validationRules()` method because Laravel already defines that name as an instance method.

## Input and output

- Validate user input as early as possible.
- Use `$request->validated()` instead of reading unchecked fields.
- Escape output by default.
- Sanitize rich HTML before storage and before trusted rendering.
- Use Eloquent or bound query parameters. Do not join user input into SQL.
- Validate uploaded file type and size before storage.

## Authorization

Authorize each resource through its owning project and `ProjectPolicy`. Do not treat route binding or hidden fields as access control.

Mirror controller authorization in the Form Request when the request owns that check. Add a negative test that confirms a non-owner receives `403`.

## Testing

Add a feature test for each endpoint, behavior change, and bug fix. For a bug, add a failing regression test first.

Cover the cases that apply:

- expected behavior;
- owner access and non-owner denial;
- validation failures;
- changed domain invariants;
- important response formats, including JSON branches.

Use the existing PHPUnit style: `RefreshDatabase`, factories, `actingAs()`, and named routes. Run `composer test`.

For interface state, assert semantic hooks such as `aria-current`, `aria-pressed`, `aria-selected`, or `data-active`. Do not depend on Tailwind class strings unless no stable semantic hook exists.

## Database work

- Eager-load relations used by a view.
- Keep established index filtering and sorting in the controller.
- Add indexes for observed query patterns.
- Use a transaction for multi-step writes that must succeed together.
- Prefer readable Eloquent queries over raw SQL.

Store a derived value only when its inputs are immutable, reads are frequent or expensive, and stale output is safe. Document every write path, backfill need, and known invalidation case. Revision summaries follow this pattern and can become stale after pruning by design.

## Tooling and documentation

Use the lockfile to select the package manager. Use tools by availability, not by operating-system name. The canonical test command is `composer test`; see `.claude/conventions/tooling.md` for shell rules.

Update the relevant guide when architecture or behavior changes. Keep explanations concise and record important limits near the feature they affect.

Maintain `CHANGELOG.md` in its established format. Describe user-visible changes, not implementation details. Commit messages should explain why the change was needed.
