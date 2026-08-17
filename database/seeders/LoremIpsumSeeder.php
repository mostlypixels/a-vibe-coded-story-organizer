<?php

namespace Database\Seeders;

use App\Enums\BookLanguage;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\User;
use App\Support\PlotlineColors;
use Database\Seeders\Concerns\BackfillsSceneWordCounts;
use Illuminate\Database\Seeder;

/**
 * A second user with a minimal project. It gives the demo data an owner other than
 * Admin, so authorization (a non-owner gets a 403) can be tried by hand in the app.
 */
class LoremIpsumSeeder extends Seeder
{
    use BackfillsSceneWordCounts;

    public function run(): void
    {
        // The factory, not firstOrCreate: email_verified_at is not fillable, so
        // firstOrCreate would drop it and lock the user out of the `verified` routes.
        $user = User::where('email', 'writer@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Writer',
                'email' => 'writer@example.com',
                'password' => bcrypt('password'),
            ]);

        // Guards a re-run of `db:seed` against a database that already has this project.
        if ($user->projects()->where('name', 'Lorem ipsum')->exists()) {
            return;
        }

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Lorem ipsum',
            'description' => '<p>Lorem ipsum.</p>',
        ]);

        // Model events are off under `db:seed`, so neither the main plotline hook
        // nor the first book hook fires. Both are seeded by hand, positions included.
        $project->plotlines()->firstWhere('is_main', true)
            ?? Plotline::create([
                'project_id' => $project->id,
                'name' => 'Main plotline',
                'is_main' => true,
                'color' => PlotlineColors::PRESETS[0],
            ]);

        // Unnamed: a sole book shows the project's name (see Book::displayName()).
        $book = $project->books()->create([
            'position' => 1,
            'language' => BookLanguage::English,
        ]);

        $act = $book->acts()->create([
            'name' => 'Lorem ipsum',
            'position' => 1,
        ]);

        $chapter = $act->chapters()->create([
            'name' => 'Lorem ipsum',
            'position' => 1,
        ]);

        $chapter->scenes()->create([
            'name' => 'Lorem ipsum',
            'contents' => 'lorem ipsum',
            'position' => 1,
        ]);

        // See BackfillsSceneWordCounts: the word_count hook does not fire while seeding.
        $this->backfillSceneWordCounts($project);
    }
}
