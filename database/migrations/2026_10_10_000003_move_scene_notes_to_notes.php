<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move each scene's `notes` field into a note linked to the scene, then drop the column.
 *
 * Query builder only: model hooks and app code can change after this migration ships.
 */
return new class extends Migration
{
    private const SCENE_TYPE = 'App\\Models\\Scene';

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('scenes')
                ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
                ->join('acts', 'acts.id', '=', 'chapters.act_id')
                ->join('books', 'books.id', '=', 'acts.book_id')
                ->whereNotNull('scenes.notes')
                ->select('scenes.id', 'scenes.name', 'scenes.notes', 'books.project_id')
                ->orderBy('scenes.id')
                ->each(function (object $scene): void {
                    if (trim($scene->notes) === '') {
                        return;
                    }

                    $now = now();

                    // Same rule as Note::titleForSceneNotes(), repeated so this migration never changes.
                    $noteId = DB::table('notes')->insertGetId([
                        'project_id' => $scene->project_id,
                        'note_category_id' => null,
                        'title' => mb_substr('Notes: '.$scene->name, 0, 255),
                        'body' => $scene->notes,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('notables')->insert([
                        'note_id' => $noteId,
                        'notable_type' => self::SCENE_TYPE,
                        'notable_id' => $scene->id,
                    ]);
                });

            // History cannot show a field that no longer exists.
            DB::table('revisions')
                ->where('revisionable_type', self::SCENE_TYPE)
                ->where('field', 'notes')
                ->delete();
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    /** Pre-V1 data is demo only: the column comes back empty. */
    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->longText('notes')->nullable()->after('contents');
        });
    }
};
