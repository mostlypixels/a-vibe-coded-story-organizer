<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers the `spec:draft` scaffolding command (see app/Console/Commands/SpecDraftCommand.php).
 *
 * Every test points `specs.path` at its own throw-away directory instead of the
 * real `.specs/` tree: writing there would leave junk behind and could trip
 * tests/Unit/SpecsStatusConsistencyTest — especially under paratest, where the
 * suite runs in parallel processes that all read the same real tree.
 */
class SpecDraftCommandTest extends TestCase
{
    /** Per-test unique temp root standing in for `.specs/` (paratest-safe). */
    private string $specsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specsRoot = sys_get_temp_dir().'/imagoldfish-specs-'.uniqid('', true);
        config(['specs.path' => $this->specsRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->specsRoot);

        parent::tearDown();
    }

    public function test_creates_a_draft_spec_with_frontmatter_title_and_description(): void
    {
        $this->artisan('spec:draft', ['name' => 'plotline-merge', '--description' => 'Merge two plotlines into one.'])
            ->expectsOutputToContain('Created .specs/draft/plotline-merge/spec.md')
            ->expectsOutputToContain('/mp-spec-expander plotline-merge')
            ->assertSuccessful();

        $spec = $this->specsRoot.'/draft/plotline-merge/spec.md';
        $this->assertFileExists($spec);

        $this->assertSame(
            "---\nstatus: draft\n---\n\n# Plotline Merge\n\nMerge two plotlines into one.\n",
            file_get_contents($spec)
        );
    }

    public function test_created_frontmatter_satisfies_the_consistency_test_expectations(): void
    {
        // SpecsStatusConsistencyTest requires the frontmatter block to open the
        // file (`\A---\n...\n---`) and declare `status: draft` for a folder
        // under draft/ — assert against the same shape it parses.
        $this->artisan('spec:draft', ['name' => 'some-feature', '--description' => 'Something.'])
            ->assertSuccessful();

        $contents = file_get_contents($this->specsRoot.'/draft/some-feature/spec.md');

        $this->assertMatchesRegularExpression('/\A---\r?\n(.*?)\r?\n---/s', $contents);
        preg_match('/\A---\r?\n(.*?)\r?\n---/s', $contents, $block);
        $this->assertSame('status: draft', $block[1]);
    }

    public function test_uses_a_placeholder_body_when_the_description_is_empty(): void
    {
        $this->artisan('spec:draft', ['name' => 'bare-feature', '--description' => ''])
            ->assertSuccessful();

        $this->assertStringContainsString(
            '<A few short paragraphs:',
            file_get_contents($this->specsRoot.'/draft/bare-feature/spec.md')
        );
    }

    public function test_rejects_names_that_are_not_kebab_case(): void
    {
        foreach (['Bad-Name', 'has_underscore', 'trailing-', '-leading', 'double--hyphen', 'spa ce'] as $invalid) {
            $this->artisan('spec:draft', ['name' => $invalid, '--description' => 'x'])
                ->expectsOutputToContain('kebab-case')
                ->assertFailed();

            $this->assertDirectoryDoesNotExist($this->specsRoot.'/draft/'.$invalid);
        }
    }

    public function test_rejects_a_name_already_used_by_a_draft(): void
    {
        File::ensureDirectoryExists($this->specsRoot.'/draft/taken-name');

        $this->artisan('spec:draft', ['name' => 'taken-name', '--description' => 'x'])
            ->expectsOutputToContain('pick a distinct name')
            ->assertFailed();
    }

    public function test_rejects_a_name_already_used_anywhere_in_the_tree(): void
    {
        // Later stages bucket features by month: .specs/<status>/<YYYY-MM>/<name>/.
        // Reusing a shipped name from a new draft would fail the consistency test.
        File::ensureDirectoryExists($this->specsRoot.'/shipped/2026-07/taken-name');

        $this->artisan('spec:draft', ['name' => 'taken-name', '--description' => 'x'])
            ->expectsOutputToContain('pick a distinct name')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->specsRoot.'/draft/taken-name');
    }
}
