<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the parent keys of the project tree and of codex aliases.
 *
 * SQLite does not index a foreign key by itself. Without these indexes, a walk
 * from a project to its scenes (`Project::sceneQuery()`) and the alias load for
 * codex matching read the whole table.
 */
return new class extends Migration
{
    /** @var array<string, string> Table => parent key column. */
    private const PARENT_KEYS = [
        'books' => 'project_id',
        'acts' => 'book_id',
        'chapters' => 'act_id',
        'codex_aliases' => 'codex_entry_id',
    ];

    public function up(): void
    {
        foreach (self::PARENT_KEYS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->index($column);
            });
        }
    }

    public function down(): void
    {
        foreach (self::PARENT_KEYS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropIndex([$column]);
            });
        }
    }
};
