<?php

namespace Database\Seeders\Concerns;

use App\Enums\FieldKind;
use App\Models\Project;
use App\Support\WordCounter;
use Illuminate\Support\Facades\DB;

/**
 * `DatabaseSeeder` uses `WithoutModelEvents`, which wraps the whole `db:seed`
 * run — every seeder it `$this->call()`s, including MelusineSeeder{En,Fr,It}
 * — in `Model::withoutEvents()`. That means `Scene::booted()`'s
 * word_count-on-save hook never fires for a scene created while seeding, even
 * though it writes through the model (`$chapter->scenes()->create()`).
 *
 * Seeding is a write path, and the word-count invariant must hold on it. So
 * each Melusine seeder backfills the counts once its story tree exists, the
 * same way the `scenes.word_count` migration backfills pre-existing rows.
 *
 * A raw `DB::table()` update, never `$scene->save()`: with model events off
 * during seeding a save would not invent a revision row the way it would
 * outside one, but matching the migration's own pattern means nobody has to
 * re-derive that reasoning by reading two places instead of one.
 */
trait BackfillsSceneWordCounts
{
    private function backfillSceneWordCounts(Project $project): void
    {
        $project->sceneQuery()->select('id', 'contents')->chunkById(500, function ($scenes) {
            foreach ($scenes as $scene) {
                DB::table('scenes')->where('id', $scene->id)->update([
                    'word_count' => WordCounter::count($scene->contents, FieldKind::Markdown),
                ]);
            }
        });
    }
}
