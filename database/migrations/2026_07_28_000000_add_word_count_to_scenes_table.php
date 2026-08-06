<?php

use App\Enums\FieldKind;
use App\Models\Scene;
use App\Support\WordCounter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `scenes.word_count` (word-count spec,
 * `.specs/shipped/2026-07/word-count/expanded/data-model.md`) and backfills it
 * for every existing scene.
 *
 * Only `scenes.contents` is ever counted — never `description` or `notes` — and
 * only `scenes` gets a column: chapter/act/project totals are a `SUM` over this
 * one column (benchmarked in the spec's grill; the widest gap versus
 * denormalising every level was 0.6 ms at 4,320 scenes / 6.3 M words).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            // unsignedInteger + default(0), never nullable: 0 is a real answer
            // ("no words yet"), so no caller has to handle "unknown".
            $table->unsignedInteger('word_count')->default(0)->after('contents');

            // Composite on (chapter_id, word_count) makes the per-chapter SUM
            // covering — the aggregate reads the index, never the `contents`
            // blob.
            $table->index(['chapter_id', 'word_count']);
        });

        // Backfill every scene that existed before this migration ran. Every
        // new scene from here on gets its word_count from the model hook, so
        // this is a one-time catch-up, not a path anything else depends on at
        // runtime.
        Scene::query()->select('id', 'contents')->chunkById(500, function ($scenes) {
            foreach ($scenes as $scene) {
                // Direct DB::table() update, never $scene->save(): a model
                // save fires HasRevisions and would write a revision row per
                // scene — a migration inventing thousands of "edits" nobody
                // made — and would bump updated_at.
                DB::table('scenes')->where('id', $scene->id)->update([
                    'word_count' => WordCounter::count($scene->contents, FieldKind::Markdown),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->dropIndex(['chapter_id', 'word_count']);
            $table->dropColumn('word_count');
        });
    }
};
