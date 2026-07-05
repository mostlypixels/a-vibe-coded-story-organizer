# Share scene — Testing

New feature test file `tests/Feature/SceneShareTest.php` (plain PHPUnit, `use RefreshDatabase`,
factories, `actingAs`, `route()` helper — mirrors `tests/Feature/ProjectTest.php`).

> [!NOTE]
> There is currently **no** `SceneTest`. Building the scene graph (project → act → chapter →
> scene) via factories is a prerequisite; check whether `ActFactory`/`ChapterFactory`/
> `SceneFactory` exist and add the missing ones. This also starts to fill the documented
> Scene coverage gap.

## Public view — `SharedSceneController@show`

- **Valid token renders the scene.** Set `share_token` + future `share_expires_at`, GET
  `route('shared.scenes.show', $token)` → 200, `assertSee($scene->name)`, and see the
  chapter/act heading. Assert Markdown contents rendered as HTML (e.g. `**x**` → `<strong>`).
- **Notes are never exposed.** Give the scene distinctive `notes` text; assert
  `assertDontSee($secretNotes)` on the public response. (Guards the privacy invariant.)
- **Description card present but rendered.** Assert the description HTML appears (collapsed is a
  client-side concern; server sends the markup).
- **Unknown token → 404.** GET with a random token that matches no scene.
- **Expired token → 410.** `share_expires_at` in the past → `assertStatus(410)` and
  `assertDontSee($scene->name)` (no data leak).
- **Public route needs no auth.** Hit the valid URL **without** `actingAs` → 200 (proves it is
  outside the auth group).

## Link generation — `SceneShareController@store`

- **Owner generates a link.** `actingAs(owner)`, POST `scenes.share.store` → redirect;
  `$scene->fresh()->share_token` is non-null and `share_expires_at` is in the future.
- **Expiry uses config.** Set `config(['sharing.scene_link_ttl' => '3 days'])`, generate, assert
  `share_expires_at` is ~3 days out (`assertTrue(...->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()))`).
- **Regeneration rotates the token.** Generate twice; assert the token changed and the old URL
  now 404s.
- **Non-owner is forbidden.** `actingAs(otherUser)`, POST → 403; token stays null.
- **Guest is redirected.** No `actingAs` → redirect to login (auth middleware).

## Link revocation — `SceneShareController@destroy`

- **Owner revokes.** With an active link, DELETE `scenes.share.destroy` → redirect; token/expiry
  null; the previously valid public URL now 404s.
- **Non-owner is forbidden.** `actingAs(otherUser)` → 403; token unchanged.

## Model helpers

- `Scene::isShared()` — true only when token set **and** expiry in the future; false when token
  null, or expiry past. Unit-style assertions on a factory-built scene.
- `Scene::shareUrl()` — null when unshared; the expected `route(...)` when shared.

## Invariants to keep in mind

- Authorization always walks up to `Project` via `ProjectPolicy` — cover the 403 path for both
  manage actions (owner succeeds / non-owner 403), per the guidelines' "always cover the
  negative case".
- No interaction with `position`, the main-plotline, or bookend invariants — sharing does not
  touch ordering or events. No regression tests needed there, but do assert deleting the scene
  removes the link implicitly (cascade) if a test already builds that path.

Run with `composer test`.
