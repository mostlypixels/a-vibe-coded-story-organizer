# Share scene — Data model

## Approach: token columns on `scenes` (decided)

**Decided:** stored token, one revocable link per scene, as two nullable columns on the existing
`scenes` table — no new aggregate. This satisfies revocation + configurable expiry, matches KISS
and "no abstraction before a second caller". The spec describes **one** share URL per scene
("*an* url is generated for the scene").

### Migration

`database/migrations/xxxx_add_share_columns_to_scenes_table.php`

```php
Schema::table('scenes', function (Blueprint $table) {
    $table->string('share_token', 64)->nullable()->unique()->after('event_id');
    $table->timestamp('share_expires_at')->nullable()->after('share_token');
});
```

- `share_token` — random, unguessable, `unique` so the public route can resolve a scene by
  token alone (`Scene::where('share_token', $token)`). `nullable` because most scenes are
  unshared. 64 chars leaves room for `Str::random(48)`-style tokens.
- `share_expires_at` — absolute expiry timestamp, computed at generation time from the config
  duration. `nullable`; a null token means "not shared".
- The `unique` index also serves the lookup query — no separate index needed.

> [!NOTE]
> Store the token **raw** (not hashed). Unlike a password, the server must look the scene up
> *by* the token from the URL, and the token is high-entropy and single-purpose. This mirrors
> how Laravel's own signed-URL / password-reset-lookup style links work. If leak-at-rest is a
> concern, hashing is possible but adds a lookup-by-hash step — call it out only if required.

## Model changes — `App\Models\Scene`

- Add to `$fillable`? **No** — never mass-assign the token. Set it explicitly.
- Add `casts`: `'share_expires_at' => 'datetime'`.
- Add small helpers (lifecycle/state, allowed in the model per guidelines):

```php
public function isShared(): bool
{
    return $this->share_token !== null
        && $this->share_expires_at !== null
        && $this->share_expires_at->isFuture();
}

public function shareUrl(): ?string
{
    return $this->share_token
        ? route('shared.scenes.show', $this->share_token)
        : null;
}
```

Token **generation** (create token + compute expiry from config) is application workflow, not a
model invariant — keep it in the controller or a tiny service (see `architecture.md`), not a
`booted()` hook.

## Configuration

Guidelines forbid hard-coded durations. Add a config key rather than a literal:

`config/sharing.php`
```php
return [
    // Default lifetime of a scene share link.
    'scene_link_ttl' => env('SCENE_SHARE_TTL', '7 days'),
];
```

Generation computes `now()->add(...)` from this value. Keep the format simple (a
`CarbonInterval`-parseable string or an integer of days — pick one and document it).

## Invariants & interactions

- **No new aggregate / policy.** The scene remains owned via `chapter.act.project`; the token
  is just an access grant on the existing row.
- **Cascade:** deleting a scene (or its chapter/act/project) drops the row and thus the token —
  no orphan links. No extra cleanup hook needed.
- **Seeding:** `MelusineSeeder` need not seed share tokens; leaving them null is the correct
  default. No `WithoutModelEvents` caveat here since generation is not a model hook.
- **Notes stay private:** the shared view must not read `scene.notes`; this is a rendering
  concern (see `ui.md`), not a schema one.

## Alternative considered: dedicated `scene_shares` table

A `scene_shares` (id, scene_id, token, expires_at, created_at) table would support multiple
concurrent links, per-link revocation, and future view tracking. It is the more DDD-pure
"small aggregate" but adds a model, a relationship, and a controller for a feature the spec
frames as one-link-per-scene. **Not chosen** — revisit only if multi-link or analytics
requirements appear later.
