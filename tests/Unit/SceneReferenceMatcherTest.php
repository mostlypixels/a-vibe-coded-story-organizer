<?php

namespace Tests\Unit;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use App\Services\SceneReferenceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Normalizer;
use Tests\TestCase;

class SceneReferenceMatcherTest extends TestCase
{
    use RefreshDatabase;

    private SceneReferenceMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new SceneReferenceMatcher;
    }

    /**
     * Create a scene with the given contents inside a specific project (scene → chapter →
     * act → project).
     */
    private function sceneIn(Project $project, ?string $contents): Scene
    {
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create(['contents' => $contents]);
    }

    /**
     * Create a codex entry with the given name and aliases in a project.
     *
     * @param  array<int, string>  $aliases
     */
    private function entryIn(Project $project, string $name, array $aliases = []): CodexEntry
    {
        $entry = CodexEntry::factory()->for($project)->create(['name' => $name]);

        foreach ($aliases as $alias) {
            CodexAlias::factory()->for($entry, 'entry')->create(['alias' => $alias]);
        }

        return $entry;
    }

    public function test_alias_matches_as_a_whole_word_but_not_as_a_substring(): void
    {
        $project = Project::factory()->create();
        $entry = $this->entryIn($project, 'Melchior', ['Mel']);
        $matches = $this->sceneIn($project, 'Mel said hello.');
        $noMatch = $this->sceneIn($project, 'She hummed a melody softly.');

        $this->matcher->syncScene($matches);
        $this->matcher->syncScene($noMatch);

        $this->assertTrue($matches->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertFalse($noMatch->codexReferences()->where('codex_entries.id', $entry->id)->exists());
    }

    public function test_entry_name_matches_not_only_its_aliases(): void
    {
        $project = Project::factory()->create();
        $entry = $this->entryIn($project, 'Mordred', ['Red Knight']);
        $scene = $this->sceneIn($project, 'Then Mordred entered the hall.');

        $this->matcher->syncScene($scene);

        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $entry->id)->exists());
    }

    public function test_matching_is_case_sensitive(): void
    {
        $project = Project::factory()->create();
        $entry = $this->entryIn($project, 'Luck', ['Mel']);
        $lowercase = $this->sceneIn($project, 'She had a lot of luck that day.');
        $wrongAliasCase = $this->sceneIn($project, 'MEL shouted from afar.');
        $exact = $this->sceneIn($project, 'Luck favoured them.');

        $this->matcher->syncScene($lowercase);
        $this->matcher->syncScene($wrongAliasCase);
        $this->matcher->syncScene($exact);

        $this->assertFalse($lowercase->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertFalse($wrongAliasCase->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertTrue($exact->codexReferences()->where('codex_entries.id', $entry->id)->exists());
    }

    public function test_aliases_shorter_than_three_characters_never_match_but_short_names_do(): void
    {
        $project = Project::factory()->create();
        $shortAlias = $this->entryIn($project, 'Alfred', ['Al']);
        $shortName = $this->entryIn($project, 'Al', []);

        $scene = $this->sceneIn($project, 'Al waved from the door.');

        $this->matcher->syncScene($scene);

        // The 2-char alias "Al" never drives a match...
        $this->assertFalse($scene->codexReferences()->where('codex_entries.id', $shortAlias->id)->exists());
        // ...but a 2-char *name* has no floor and still matches.
        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $shortName->id)->exists());
    }

    public function test_unicode_whole_word_and_punctuation_adjacency(): void
    {
        $project = Project::factory()->create();
        $entry = $this->entryIn($project, 'Mélusine');
        $plural = $this->sceneIn($project, 'The Mélusines gathered by the river.');
        $punctuation = $this->sceneIn($project, '"Mélusine," she said quietly.');

        $this->matcher->syncScene($plural);
        $this->matcher->syncScene($punctuation);

        $this->assertFalse($plural->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertTrue($punctuation->codexReferences()->where('codex_entries.id', $entry->id)->exists());
    }

    public function test_hyphenated_alias_matches_as_one_unit_and_a_bare_segment_does_not(): void
    {
        $project = Project::factory()->create();
        $hyphenated = $this->entryIn($project, 'Captain', ['Jean-Luc']);
        $bareSegment = $this->entryIn($project, 'Jean');

        $scene = $this->sceneIn($project, 'Jean-Luc took the helm.');

        $this->matcher->syncScene($scene);

        // The hyphenated alias matches the whole "Jean-Luc"...
        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $hyphenated->id)->exists());
        // ...but the bare "Jean" entry does not match inside it (hyphen is part of the word).
        $this->assertFalse($scene->codexReferences()->where('codex_entries.id', $bareSegment->id)->exists());
    }

    public function test_two_entries_sharing_identical_term_both_link(): void
    {
        $project = Project::factory()->create();
        $first = $this->entryIn($project, 'The Raven', ['Corvus']);
        $second = $this->entryIn($project, 'A different entry', ['Corvus']);

        $scene = $this->sceneIn($project, 'Corvus circled overhead.');

        $this->matcher->syncScene($scene);

        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $first->id)->exists());
        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $second->id)->exists());
    }

    public function test_no_cross_project_matching(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $entryA = $this->entryIn($projectA, 'Gandalf');

        $sceneB = $this->sceneIn($projectB, 'Gandalf raised his staff.');

        $this->matcher->syncScene($sceneB);

        $this->assertFalse($sceneB->codexReferences()->where('codex_entries.id', $entryA->id)->exists());
        $this->assertSame(0, $sceneB->codexReferences()->count());
    }

    public function test_full_resync_removes_stale_rows(): void
    {
        $project = Project::factory()->create();
        $stale = $this->entryIn($project, 'Formerlymentioned');
        $scene = $this->sceneIn($project, 'This text mentions nobody by name.');

        // Seed a stale pivot row directly (as if a previous save matched it).
        $scene->codexReferences()->attach($stale);
        $this->assertTrue($scene->codexReferences()->where('codex_entries.id', $stale->id)->exists());

        $this->matcher->syncScene($scene);

        // A full sync (not attach-only) must drop the now-unmatched row.
        $this->assertFalse($scene->codexReferences()->where('codex_entries.id', $stale->id)->exists());
        $this->assertSame(0, $scene->codexReferences()->count());
    }

    public function test_null_and_empty_contents_produce_no_matches(): void
    {
        $project = Project::factory()->create();
        $this->entryIn($project, 'Anyone');
        $nullScene = $this->sceneIn($project, null);
        $emptyScene = $this->sceneIn($project, '');

        $this->matcher->syncScene($nullScene);
        $this->matcher->syncScene($emptyScene);

        $this->assertSame(0, $nullScene->codexReferences()->count());
        $this->assertSame(0, $emptyScene->codexReferences()->count());
    }

    public function test_sync_project_updates_every_scene_at_once(): void
    {
        $project = Project::factory()->create();
        $entry = $this->entryIn($project, 'Beacon');
        $firstScene = $this->sceneIn($project, 'The Beacon burned bright.');
        $secondScene = $this->sceneIn($project, 'Far away, the Beacon answered.');
        $unrelated = $this->sceneIn($project, 'Nothing to see here.');

        $this->matcher->syncProject($project);

        $this->assertTrue($firstScene->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertTrue($secondScene->codexReferences()->where('codex_entries.id', $entry->id)->exists());
        $this->assertSame(0, $unrelated->codexReferences()->count());
    }

    public function test_nfc_and_nfd_forms_match_across_the_encoding_divide(): void
    {
        $project = Project::factory()->create();

        // "Mélusine" with the é as a precomposed NFC codepoint vs. base-letter + combining
        // accent (NFD). They are visually identical but byte-different.
        $nfc = Normalizer::normalize('Mélusine', Normalizer::FORM_C);
        $nfd = Normalizer::normalize('Mélusine', Normalizer::FORM_D);
        $this->assertNotSame($nfc, $nfd, 'The two forms must differ in bytes for this test to be meaningful.');

        // Alias stored NFD, scene text NFC.
        $entryOne = $this->entryIn($project, 'Placeholder one', [$nfd]);
        $sceneOne = $this->sceneIn($project, "Here comes {$nfc} again.");

        // Alias stored NFC, scene text NFD.
        $entryTwo = $this->entryIn($project, 'Placeholder two', [$nfc]);
        $sceneTwo = $this->sceneIn($project, "Here comes {$nfd} again.");

        $this->matcher->syncScene($sceneOne);
        $this->matcher->syncScene($sceneTwo);

        $this->assertTrue($sceneOne->codexReferences()->where('codex_entries.id', $entryOne->id)->exists());
        $this->assertTrue($sceneTwo->codexReferences()->where('codex_entries.id', $entryTwo->id)->exists());
    }

    public function test_malformed_utf8_contents_do_not_throw_and_log_a_warning(): void
    {
        $project = Project::factory()->create();
        $entry = $this->entryIn($project, 'Anyone');

        // An invalid UTF-8 byte sequence (a lone 0x80 continuation byte).
        $scene = $this->sceneIn($project, "valid text \x80 broken");

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => ($context['scene_id'] ?? null) === $scene->id);

        $this->matcher->syncScene($scene);

        $this->assertSame(0, $scene->codexReferences()->count());
        $this->assertFalse($scene->codexReferences()->where('codex_entries.id', $entry->id)->exists());
    }
}
