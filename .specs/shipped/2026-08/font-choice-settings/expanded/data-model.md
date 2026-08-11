# Data model

One migration, four nullable string columns on `users`, no new table.

```php
// database/migrations/2026_08_XX_000000_add_font_preferences_to_users_table.php
$table->string('ui_font')->nullable()->after('theme_slug');
$table->string('manuscript_font')->nullable()->after('ui_font');
$table->string('text_scale')->nullable()->after('manuscript_font');
$table->string('manuscript_leading')->nullable()->after('text_scale');
```

- **Never write a default value into the column.** `null` means "follow the config
  default", so changing `config('fonts.default_ui')` still reaches everyone who never
  picked. This is the rule `theme_slug` and `timezone` already document in their own
  migrations — copy the reasoning, do not restate it at length.
- No index: these are read only via the already-loaded authenticated user.
- No enum column / no DB constraint. SQLite, MySQL and Postgres are all supported
  (`multiple-database-engines`), and the list changes in config; validation is the
  Form Request's job and a stale value falls back rather than throwing.
- Add all four to `User::$fillable`. `AppearanceController::update()` mass-assigns
  `$request->validated()`, which is the only writer.
- `UserFactory` needs nothing — `null` is the realistic default state.
