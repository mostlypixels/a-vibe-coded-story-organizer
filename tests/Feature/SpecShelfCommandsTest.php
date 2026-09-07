<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers `spec:shelve` and `spec:unshelve` (see app/Services/SpecShelf.php).
 *
 * Every test points `specs.path` at its own throw-away directory instead of the
 * real `.specs/` tree: moving folders there would leave junk behind and trip
 * tests/Unit/SpecsStatusConsistencyTest, which reads the real tree in parallel
 * processes under paratest.
 */
class SpecShelfCommandsTest extends TestCase
{
    private string $specsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specsRoot = sys_get_temp_dir().'/imagoldfish-shelf-'.uniqid('', true);
        config(['specs.path' => $this->specsRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->specsRoot);

        parent::tearDown();
    }

    private function makeDraft(string $name, string $body = "# Plotline Merge\n\nMerge two plotlines.\n"): string
    {
        File::ensureDirectoryExists("$this->specsRoot/draft/$name");
        File::put("$this->specsRoot/draft/$name/spec.md", "---\nstatus: draft\n---\n\n$body");

        return "$this->specsRoot/draft/$name/spec.md";
    }

    public function test_shelving_moves_the_folder_and_restamps_the_status(): void
    {
        $this->makeDraft('plotline-merge');

        $this->artisan('spec:shelve', ['name' => 'plotline-merge'])
            ->expectsOutputToContain('Shelved: .specs/shelved/plotline-merge/spec.md')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist("$this->specsRoot/draft/plotline-merge");
        $this->assertSame(
            "---\nstatus: shelved\n---\n\n# Plotline Merge\n\nMerge two plotlines.\n",
            file_get_contents("$this->specsRoot/shelved/plotline-merge/spec.md")
        );
    }

    public function test_shelving_carries_the_whole_folder_not_only_the_spec(): void
    {
        $this->makeDraft('plotline-merge');
        File::put("$this->specsRoot/draft/plotline-merge/research.html", '<p>notes</p>');

        $this->artisan('spec:shelve', ['name' => 'plotline-merge'])->assertSuccessful();

        $this->assertFileExists("$this->specsRoot/shelved/plotline-merge/research.html");
    }

    public function test_a_reason_becomes_a_note_under_the_title(): void
    {
        $this->makeDraft('plotline-merge');

        $this->artisan('spec:shelve', ['name' => 'plotline-merge', '--reason' => 'Waits for the codex rework.'])
            ->assertSuccessful();

        $this->assertSame(
            "---\nstatus: shelved\n---\n\n# Plotline Merge\n\n> [!NOTE]\n> **Shelved.** Waits for the codex rework.\n\nMerge two plotlines.\n",
            file_get_contents("$this->specsRoot/shelved/plotline-merge/spec.md")
        );
    }

    public function test_unshelving_restores_the_draft_and_removes_the_note(): void
    {
        $this->makeDraft('plotline-merge');
        $this->artisan('spec:shelve', ['name' => 'plotline-merge', '--reason' => 'Later.'])->assertSuccessful();

        $this->artisan('spec:unshelve', ['name' => 'plotline-merge'])
            ->expectsOutputToContain('Unshelved: .specs/draft/plotline-merge/spec.md')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist("$this->specsRoot/shelved/plotline-merge");
        $this->assertSame(
            "---\nstatus: draft\n---\n\n# Plotline Merge\n\nMerge two plotlines.\n",
            file_get_contents("$this->specsRoot/draft/plotline-merge/spec.md")
        );
    }

    public function test_unshelving_keeps_a_note_the_author_wrote_by_hand(): void
    {
        $this->makeDraft('name-sorting', "# Name Sorting\n\n> [!NOTE]\n> **Low priority.** No real data yet.\n\nSort by reading order.\n");
        $this->artisan('spec:shelve', ['name' => 'name-sorting'])->assertSuccessful();

        $this->artisan('spec:unshelve', ['name' => 'name-sorting'])->assertSuccessful();

        $this->assertStringContainsString(
            "> [!NOTE]\n> **Low priority.** No real data yet.",
            file_get_contents("$this->specsRoot/draft/name-sorting/spec.md")
        );
    }

    public function test_only_a_draft_can_be_shelved(): void
    {
        File::ensureDirectoryExists("$this->specsRoot/expanded/2026-09/scoped-search");
        File::put("$this->specsRoot/expanded/2026-09/scoped-search/spec.md", "---\nstatus: expanded\nexpanded: 2026-09-07\n---\n\n# Scoped Search\n");

        $this->artisan('spec:shelve', ['name' => 'scoped-search'])
            ->expectsOutputToContain('not under .specs/draft/')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist("$this->specsRoot/shelved/scoped-search");
    }

    public function test_shelving_an_unknown_feature_fails(): void
    {
        $this->artisan('spec:shelve', ['name' => 'no-such-feature'])
            ->expectsOutputToContain("No feature named 'no-such-feature'")
            ->assertFailed();
    }

    public function test_unshelving_something_that_is_not_shelved_fails(): void
    {
        $this->makeDraft('plotline-merge');

        $this->artisan('spec:unshelve', ['name' => 'plotline-merge'])
            ->expectsOutputToContain('not under .specs/shelved/')
            ->assertFailed();
    }

    public function test_both_commands_reject_a_name_that_is_not_kebab_case(): void
    {
        $this->artisan('spec:shelve', ['name' => 'Bad_Name'])
            ->expectsOutputToContain('kebab-case')
            ->assertFailed();

        $this->artisan('spec:unshelve', ['name' => 'Bad_Name'])
            ->expectsOutputToContain('kebab-case')
            ->assertFailed();
    }

    public function test_a_shelved_name_still_blocks_a_new_draft(): void
    {
        // Shelving must not free the name: the tree-wide uniqueness rule is what
        // makes locating a feature by name resolve to one folder.
        $this->makeDraft('plotline-merge');
        $this->artisan('spec:shelve', ['name' => 'plotline-merge'])->assertSuccessful();

        $this->artisan('spec:draft', ['name' => 'plotline-merge', '--description' => 'x'])
            ->expectsOutputToContain('pick a distinct name')
            ->assertFailed();
    }
}
