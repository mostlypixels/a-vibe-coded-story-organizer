<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the `.specs/` layout: a feature lives under a status subfolder
 * (`.specs/<status>/<name>/`) and its `spec.md` frontmatter must declare the same
 * `status:`. The folder location and the frontmatter encode the lifecycle stage
 * redundantly, so they can drift — this test is the reconciler that catches it
 * (e.g. a feature implemented and moved to `shipped/` but left stamped `planned`).
 * It also guards name uniqueness across the tree: locating a feature by name must resolve
 * to one folder, so no feature name may appear under two different status subfolders.
 *
 * Plain filesystem assertions, no database — hence a Unit test that runs under
 * `composer test` (and therefore CI) with no extra wiring.
 */
class SpecsStatusConsistencyTest extends TestCase
{
    /** The four lifecycle stages, which are also the only allowed status subfolders. */
    private const KNOWN_STATUSES = ['draft', 'expanded', 'planned', 'shipped'];

    private function specsRoot(): string
    {
        // tests/Unit → repo root is two levels up.
        return dirname(__DIR__, 2).'/.specs';
    }

    public function test_specs_root_holds_only_status_subfolders(): void
    {
        foreach (glob($this->specsRoot().'/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);

            $this->assertContains(
                $name,
                self::KNOWN_STATUSES,
                ".specs/$name/ is not a valid status folder. The root may hold only "
                .implode(', ', self::KNOWN_STATUSES)
                .' (feature folders go one level deeper, under a status).'
            );
        }
    }

    public function test_no_feature_folder_sits_directly_under_specs_root(): void
    {
        // A spec.md exactly one level under .specs/ means a feature was not filed
        // under a status subfolder.
        foreach (glob($this->specsRoot().'/*/spec.md') as $misplaced) {
            $this->fail(
                'Feature folder '.dirname($misplaced).' sits directly under .specs/; '
                .'move it under a status subfolder (.specs/<status>/<name>/).'
            );
        }

        $this->assertTrue(true, 'No misplaced feature folders.');
    }

    public function test_no_feature_name_is_reused_across_status_folders(): void
    {
        // A feature is located by the glob `.specs/*/<name>/`, so that name must resolve to
        // exactly one folder. The collision happens when new work reuses a name that already
        // shipped (e.g. a fresh `draft/foo` beside an existing `shipped/foo`): the glob then
        // matches two folders and every skill that locates by name silently picks the wrong
        // one — or clobbers the shipped spec on the next `git mv`. Catch it here instead.
        $foldersByName = [];

        foreach (glob($this->specsRoot().'/*/*', GLOB_ONLYDIR) as $dir) {
            $status = basename(dirname($dir));

            // Only consider feature folders under a known status; unknown roots are already
            // reported by test_specs_root_holds_only_status_subfolders.
            if (! in_array($status, self::KNOWN_STATUSES, true)) {
                continue;
            }

            $foldersByName[basename($dir)][] = $status;
        }

        foreach ($foldersByName as $name => $statuses) {
            $this->assertCount(
                1,
                $statuses,
                "Feature '$name' exists under multiple status folders (".implode(', ', $statuses).'). '
                .'Feature names must be unique across the whole .specs/ tree so locating by name '
                .'resolves to one folder. The pipeline auto-suffixes a colliding name with the move '
                ."date ('$name-YYYY-MM-DD') when a stage moves the folder; if you created this "
                .'collision by hand, rename the newer folder the same way (see .specs/README.md → '
                .'Name-collision handling).'
            );
        }
    }

    public function test_every_spec_frontmatter_status_matches_its_status_folder(): void
    {
        $specs = glob($this->specsRoot().'/*/*/spec.md');

        $this->assertNotEmpty($specs, 'Expected at least one feature spec under .specs/<status>/<name>/.');

        foreach ($specs as $spec) {
            $status = basename(dirname($spec, 2)); // .../<status>/<name>/spec.md
            $relative = '.specs/'.$status.'/'.basename(dirname($spec)).'/spec.md';

            // Only reconcile specs that live under a known status folder; an unknown
            // folder is already reported by test_specs_root_holds_only_status_subfolders.
            if (! in_array($status, self::KNOWN_STATUSES, true)) {
                continue;
            }

            $declared = $this->frontmatterStatus($spec);

            $this->assertNotNull(
                $declared,
                "$relative has no `status:` frontmatter (its folder implies '$status')."
            );

            $this->assertSame(
                $status,
                $declared,
                "$relative declares status '$declared' but lives in '$status/' — "
                .'the frontmatter stamp and the folder disagree.'
            );
        }
    }

    /**
     * Read the `status:` value from a spec's leading YAML frontmatter block, or null
     * when the file has no frontmatter or no status line.
     */
    private function frontmatterStatus(string $file): ?string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        // Frontmatter must be the very first thing in the file: --- ... ---.
        if (! preg_match('/\A---\r?\n(.*?)\r?\n---/s', $contents, $block)) {
            return null;
        }

        if (! preg_match('/^status:\s*(\S+)/m', $block[1], $match)) {
            return null;
        }

        return trim($match[1], "\"' ");
    }
}
