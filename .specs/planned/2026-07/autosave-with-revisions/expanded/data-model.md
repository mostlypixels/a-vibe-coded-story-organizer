---
title: Autosave With Revisions — Data Model
---

# Data model

All decisions here are settled in `handoff.md` §1, §4, §9.2, §9.8, §9.9, §9.11; this file
turns them into concrete migrations/models grounded in what exists today.

## `revisions` table

New migration `database/migrations/2026_07_XX_000000_create_revisions_table.php`:

```php
Schema::create('revisions', function (Blueprint $table) {
    $table->id();
    $table->string('revisionable_type');
    $table->unsignedBigInteger('revisionable_id');
    $table->string('field');
    $table->longText('value');
    $table->unsignedInteger('size_bytes');
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->string('label')->nullable();
    $table->string('origin'); // App\Enums\RevisionOrigin, string-backed
    $table->timestamp('created_at')->useCurrent();

    $table->index(['revisionable_type', 'revisionable_id', 'field', 'created_at'], 'revisions_entity_field_idx');
    $table->index('project_id');
    $table->index('label');
});
```

Notes:

* No `updated_at` — a revision is immutable once its coalescing window closes (§2.2);
  the coalescing *overwrite* happens via a plain `UPDATE ... SET value = ?, size_bytes =
  ?` against the still-open row, not an Eloquent `touch()`.
* `project_id` gets a real FK with `cascadeOnDelete()` — deliberately not polymorphic —
  because (per `handoff.md`'s "codebase constraints") deleting a `Project` cascades to
  `acts`/`chapters`/`scenes` at the DB level without firing Eloquent events, so a
  `deleting` hook on `Revision` would silently never run. The FK is the only mechanism
  that reliably sweeps the bulk-delete case. For `Project` itself, `project_id` equals
  its own `id` — set explicitly in `Revision::forEntity()` (see `architecture.md`), not
  inferred, since a self-referential FK still needs a value.
* No FK on `revisionable_type`/`revisionable_id` — standard polymorphic shape, matches
  the existing `scene_codex_entry`/pivot conventions in not enforcing referential
  integrity on polymorphic columns.
* `value` and the 14 live columns it revisions are `longText()`, not `text()` — see
  "Widen the long-text columns" below.

## `App\Models\Revision`

```php
namespace App\Models;

use App\Enums\RevisionOrigin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class Revision extends Model
{
    use MassPrunable;

    public $timestamps = false; // created_at only, set explicitly

    protected $fillable = [
        'revisionable_type', 'revisionable_id', 'field', 'value', 'size_bytes',
        'project_id', 'user_id', 'label', 'origin', 'created_at',
    ];

    protected function casts(): array
    {
        return ['origin' => RevisionOrigin::class, 'created_at' => 'datetime'];
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    // MassPrunable: the query, not a per-row callback — matches handoff.md §4.2.
    public function prunable(): Builder
    {
        return static::query()
            ->where('origin', RevisionOrigin::Automatic)
            ->whereNull('label')
            ->where('created_at', '<', now()->subDays(RevisionSetting::current()->retention_days))
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('revisions')
                    ->groupBy(['revisionable_type', 'revisionable_id', 'field']);
            });
    }
}
```

The `whereNotIn(... MAX(id) group by ...)` subquery is the portable "never prune the
newest revision of a field" rule from `handoff.md` §4.2 — no window function, valid on
all five `multiple-database-engines` targets.

## `App\Enums\RevisionOrigin`

```php
namespace App\Enums;

enum RevisionOrigin: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Revert = 'revert';
    case Import = 'import';
    case Baseline = 'baseline';
}
```

Matches the `SceneStatus`-style string-backed enum already in `app/Enums`. Only
`Automatic` is ever prunable (§1.2).

## Widen the long-text columns

Every column `AutosavableFields` registers, plus `revisions.value`, must be
`longText()`. Today they are all `$table->text()` — 65,535-byte cap on MySQL/MariaDB
(`handoff.md` §9.8, §11.1). One migration,
`2026_07_XX_000001_widen_long_text_columns_to_long_text.php`, changing all 14 columns
across `projects`, `acts`, `chapters`, `plotlines`, `events`, `scenes`,
`codex_entries` via `$table->longText('...')->change()` (requires `doctrine/dbal`,
already a Laravel `change()` dependency — confirm it's present, see `open-questions.md`).
Per `handoff.md`, this is a real change on MySQL/MariaDB only; pgsql/sqlite/sqlsrv
already map `text()`/`longText()` identically.

## `size_bytes`

Set from `strlen($value)` on every insert **and** every coalescing overwrite — same
write path, one line, in whatever service method performs the write (see
`architecture.md`'s `RevisionRecorder`). This is what makes the storage panel and purge
preview a plain `SUM(size_bytes)` group-by-origin query, portable across all five
engines without `LENGTH()`/`octet_length()`/`DATALENGTH()` differences (§9.9).

## Baseline seeding + backfill

* **Going forward:** the first-ever revision written for a given `(entity, field)`
  is preceded by inserting a `baseline` revision holding the **pre-edit** DB value, with
  `created_at = $model->updated_at` and `user_id` = the project's owner (§9.2). This
  check-then-seed logic lives once, in the same service both the live write path and the
  backfill migration call — see `RevisionRecorder::ensureBaseline()` in
  `architecture.md`.
* **Backfill migration** `2026_07_XX_000002_backfill_baseline_revisions.php`: for every
  existing row of every registered model, for every registered field with a non-empty
  value, call `RevisionRecorder::ensureBaseline()`. Must batch (`chunkById`) — this
  touches every Project/Act/Chapter/Scene/Event/CodexEntry/Plotline row in an existing
  install. Populates `size_bytes` on every backfilled row too (§9.9's requirement that
  the backfill populate it).

## `RevisionSetting` singleton

Mirrors `ImportSetting` exactly (`app/Models/ImportSetting.php`, read above):

```php
class RevisionSetting extends Model
{
    protected $fillable = ['retention_days'];

    public static function current(): self
    {
        return static::firstOr(fn () => static::create([
            'retention_days' => config('revisions.retention_days'),
        ]));
    }
}
```

Migration `create_revision_settings_table` (nullable-free, one row, `retention_days`
unsigned int default from config, mirroring `import_settings`). Bounds enforced in
`UpdateRevisionSettingRequest`: `min:7`, `max:3650` — no "0 = never" (§9.11).

## `config/revisions.php`

Mirrors `config/import.php`'s shape:

```php
return [
    'retention_days' => (int) env('REVISIONS_RETENTION_DAYS', 90),

    'windows' => [
        'Scene.contents' => 60, // seconds
        'default' => 300,
    ],

    'caps' => [
        // per-field character cap, enforced identically by the autosave endpoint
        // and the existing Form Requests (handoff.md §9.8)
        'Scene.contents' => 1_000_000,
        'Project.rights' => 1_000,
        'default' => 100_000, // descriptions
    ],
];
```

`App\Support\AutosavableFields` (see `architecture.md`) reads these, keyed
`Model.field` with a `default` fallback — never hard-coded per field in the controller.

## `project_id` resolution for non-Project entities

The `HasRevisions` trait (see `architecture.md`) resolves each model's owning project,
per `handoff.md` §3.1:

| Model | Path |
|---|---|
| `Act`, `Event`, `CodexEntry`, `Plotline` | `->project` (direct relation, confirmed above) |
| `Chapter` | `->act->project` |
| `Scene` | `->chapter->act->project` |
| `Project` | itself |

All confirmed against the actual models read in this session (`app/Models/Act.php`,
`Chapter.php`, `Scene.php`, `Project.php`).
