# Data model

## `users.editor_layout`

One nullable JSON column. Same pattern as `page_size` / `page_width`: a user preference with no owning project.

Migration `add_editor_layout_to_users_table`: `$table->json('editor_layout')->nullable()->after('page_width');`. Null means defaults, so no backfill.

Shape:

```json
{
  "left":  { "width": 280, "collapsed": false },
  "right": { "width": 360, "collapsed": false },
  "sections": { "codex": false, "notes": false }
}
```

- `width` in CSS pixels. `sections.*` is "collapsed".
- Add `editor_layout` to `User::$fillable`. Cast `array`.

## `App\Support\EditorLayout`

The single door from the stored column to view values, like `App\Support\PageWidth`.

```php
final class EditorLayout
{
    public static function resolve(?array $stored): array; // full shape, defaults filled, widths clamped
    public static function rules(): array;                 // for the Form Request
}
```

- Limits and defaults in `config/editor.php`: `min_width` 200, `max_width` 640, the defaults above.
- `resolve()` drops unknown keys and clamps out-of-range widths. The browser sends the window width it saw, so a value valid then can be too wide now. CSS also caps each panel with `max-width: 40vw`.

## Nothing else

- No new table. Open cards are not stored on the server (see `architecture.md`).
- No change to scenes, codex or notes. Main plotline and position ordering are not touched.
- Seeders: none. `MelusineSeeder` users keep null.
