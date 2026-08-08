# 01 — The writer's timezone

## Scope

- Migration: `users.timezone`, `string`, nullable, no default.
- `User::$fillable` gains `timezone`.
- `App\Support\WriterDay` — `for(User): CarbonImmutable` and `dateFor(User): string`.
- `ProfileUpdateRequest` gains `['nullable', 'timezone']`.
- The profile form gains a grouped `x-select` over `DateTimeZone::listIdentifiers()`, first
  option *"Use the site default"* → stored `null`.
- `UserFactory` state for a non-UTC zone (the later timezone tests need it).

**Not** in this task: anything that *uses* the date. No snapshots table yet.

## Depends on

Nothing.

## Key decisions

- `null` means "follow `config('app.timezone')`". **Never write the default into the
  column** — copy the reasoning from the `users.theme_slug` migration docblock, which exists
  for the same trap.
- Per-user, not per-project: one working day across all a writer's projects.
- `WriterDay` exists so the recorder, the default range and "is today's row there?" cannot
  drift. Do not inline `now()->timezone(...)` anywhere later.
- Changing the timezone never rewrites past data.

## Consult

`expanded/data-model.md` → *Changed: `users`* · `expanded/architecture.md` → *The writer's day*
· `expanded/ui.md` → *Timezone selector*

## Tests

- `WriterDay` under `travelTo()`: Auckland, Los Angeles, and `null` → app default.
- Profile: a valid identifier persists; `""` stores `null`; `Europe/NotAPlace` →
  `assertSessionHasErrors('timezone')`.
- Non-owner cannot edit another user's profile (existing `ProfileTest` posture).
